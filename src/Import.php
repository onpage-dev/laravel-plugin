<?php

namespace OnPage;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Import extends Command
{
    protected $signature = 'onpage:import {snapshot_file?} {--regenerate-snapshot} {--force} {--anyway}';
    protected $danger = false;
    protected $description = 'Import data from On Page';
    private $current_token;
    private $snapshot;
    private $changes = [];
    private $field_key_to_field = [];

    function getLastToken(): ?string
    {
        if (!Storage::disk('local')->exists('snapshots/last_token.txt')) {
            return null;
        }
        return Storage::disk('local')->get('snapshots/last_token.txt');
    }

    function finalizeImport()
    {
        Storage::disk('local')->put('snapshots/last_token.txt', $this->current_token);
        Storage::disk('local')->put("snapshots/$this->current_token", json_encode($this->snapshot));
    }

    function createBar($count)
    {
        $bar = $this->output->createProgressBar($count);
        $bar->setBarWidth(1000);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        return $bar;
    }

    public function handle()
    {
        $this->loadSnapshot();

        $this->comment("Computing changes...");
        $this->computeAllChanges();
        if (!$this->option('force') && $this->danger) {
            $confirm = $this->ask('Do you want to proceed? (y/N)');
            if (!in_array(strtolower($confirm), ['y', 'yes'])) {
                return 1;
            }
        }

        $this->comment("Importing project {$this->snapshot->label}");
        $this->importSchema(Models\Resource::class, [
            'schema_id',
            'name',
            'label',
            'labels',
        ]);

        $this->importSchema(Models\Field::class, [
            'resource_id',
            'name',
            'type',
            'label',
            'labels',
            'description',
            'descriptions',
            'unit',
            'opts',
            'order',
            'is_multiple',
            'is_translatable',
            'rel_res_id',
        ]);

        $this->importSchema(Models\FieldFolders::class, [
            'resource_id',
            'label',
            'type',
            'labels',
        ]);

        // Refresh resource cache
        Cache::refresh();

        $this->generateModels();

        $this->importThings();
        $this->importRelations();

        $this->finalizeImport();
        $this->comment("Snapshot saved.");
    }

    function generateModels()
    {
        foreach ($this->snapshot->resources as $res) {
            generate_model_file($res);
        }
    }

    function computeAllChanges()
    {
        $resources = collect($this->snapshot->resources);
        $this->computeChanges(
            Models\Resource::class,
            $resources,
            [
                'name',
            ]
        );

        $field_folders = $resources->pluck('field_folders')->collapse();
        $this->computeChanges(
            Models\FieldFolders::class,
            $field_folders,
            [
                'label',
                'fids'
            ]
        );

        $fields = $resources->pluck('fields')->collapse();
        $this->computeChanges(
            Models\Field::class,
            $fields,
            [
                'name',
                'type',
                'is_multiple',
                'is_translatable',
            ]
        );

        $things = $resources->pluck('data')->collapse();
        $this->computeChanges(
            Models\Thing::class,
            $things,
            [
                'default_folder_id'
            ]
        );
    }

    function loadSnapshot()
    {
        $snapshot_file = $this->argument('snapshot_file');
        if (!$snapshot_file) {
            $token = config('onpage.token');
            $info = null;
            if ($this->option('regenerate-snapshot')) {
                $this->comment('Getting snapshot info...');
                $info = curl_get("https://api.onpage.it/view/$token/generate-snapshot", function ($ch, $httpCode, $errorMessage) {
                    throw new \Exception("Unable to generate snapshot: HTTP $httpCode - $errorMessage. Please check the token and company name is correct.");
                });
            } else {
                $this->comment('Getting snapshot info...');
                $info = curl_get("https://api.onpage.it/view/$token/dist", function ($ch, $httpCode, $errorMessage) {
                    throw new \Exception("Unable to get snapshot information: HTTP $httpCode - $errorMessage. Please check the token and company name is correct.");
                });
            }
            $this->current_token = $info->token;

            if ($this->getLastToken() == $this->current_token && !$this->option('anyway')) {
                $this->comment("No updates found, use --anyway to re-import the latest snapshot available");
                exit;
            }


            $this->comment('Downloading snapshot...');
            $this->snapshot = curl_get("https://storage.onpage.it/{$this->current_token}", function ($ch, $httpCode, $errorMessage) {
                throw new \Exception("Unable to download snapshot: HTTP $httpCode - $errorMessage. Please check the token and company name is correct.");
            });
        } else {
            $this->comment("Loading snapshot...");
            $this->current_token = Storage::get($snapshot_file);
            $this->snapshot = json_decode(Storage::disk('local')->get($snapshot_file));
        }
    }

    function importSchema(string $model, array $fields)
    {
        $model_name = collect(explode('\\', $model))->last();
        $changes = $this->changes[$model_name];

        $this->comment("Importing {$model_name}s...");
        $bar = $this->createBar($changes->items->count());
        foreach ($changes->items as $item) {
            $new_item = $model::withoutGlobalScopes()->updateOrCreate([
                'id' => $item->id,
            ], collect($item)->only($fields)->all());

            if ($model == Models\FieldFolders::class) {
                $new_item->fields()->detach();

                foreach ($item->form_fields as $field_id) {
                    $new_item->fields()->attach([$field_id => ["type" => "form_fields"]]);
                }

                foreach ($item->arrow_fields as $field_id) {
                    $new_item->fields()->attach([$field_id => ["type" => "arrow_fields"]]);
                }
            }
            $bar->advance();
        }
        $bar->finish();
        echo "\n";

        if (count($changes->del)) {
            $this->comment("Deleting old {$model_name}s...");
            $bar = $this->createBar(count($changes->del));
            foreach ($changes->del as $item) {
                $model::where('id', $item->id)->delete();
                $bar->advance();
            }
            $bar->finish();
            echo "\n";
        }
    }

    function importThings()
    {
        $insert = [];
        $flush = function () use (&$insert) {
            // echo "tids...\n";
            $tids = collect($insert)->pluck('thing_id')->unique();
            // echo "deleting...\n";
            DB::beginTransaction();
            Models\Value::whereIn('thing_id', $tids)->delete();
            Models\Value::insert($insert);
            $insert = [];
            DB::commit();
        };

        $this->comment("Importing things...");
        $changes = $this->changes['Thing'];
        $existing_tids = Models\Thing::query()->withoutGlobalScopes()->get()->keyBy('id');
        $bar = $this->createBar($changes->items->count());

        $chunk_size = 1000;
        foreach ($changes->items->chunk($chunk_size) as $chunk) {
            $things = [];
            foreach ($chunk as $thing) {
                $thing_assoc = collect($thing)->only([
                    'id',
                    'order',
                    'default_folder_id',
                    'resource_id',
                    'created_at',
                    'updated_at',
                ])->all();

                if (!isset($existing_tids[$thing->id])) {
                    $things['insert'][] = $thing_assoc;
                } else if (
                    $existing_tids[$thing->id]->order !== $thing->order ||
                    $existing_tids[$thing->id]->default_folder_id !== $thing->default_folder_id ||
                    $existing_tids[$thing->id]->updated_at !== $thing->updated_at
                ) {
                    $things['update'][] = $thing_assoc;
                }
            }

            if (isset($things['insert']) && !empty($things['insert'])) {
                Models\Thing::insert($things['insert']);
                $this->comment("Inserted ".count($things['insert'])." records -> [".$things['insert'][0]['id'].", ..., ".$things['insert'][count($things['insert'])-1]['id']."]");
            }

            if (isset($things['update']) && !empty($things['update'])) {
                Models\Thing::upsert($things['update'], [ 'id' ], [ 'order', 'default_folder_id', 'resource_id', 'created_at', 'updated_at' ] );
                $this->comment("Updated ".count($things['update'])." records -> [".$things['update'][0]['id'].", ..., ".$things['update'][count($things['update'])-1]['id']."]");
            }

            foreach ($chunk as $thing) {
                $this->computeThingValues($thing, $insert);
                if (count($insert) > 5000) {
                    $flush();
                }
            }

            $bar->advance();
        }

        $flush();
        $bar->finish();
        echo "\n";

        if (count($changes->del)) {
            $this->comment('Deleting old things...');
            foreach (array_chunk($changes->del, $chunk_size) as $chunk) {
                $ids = collect($chunk)->pluck('id');
                Models\Thing::whereIn('id', $ids)->delete();
            }
        }
    }

    function computeThingValues(object $thing, array &$insert)
    {
        foreach ($thing->fields as $field_key => $values) {
            $field = null;
            $lang = null;
            if (isset($this->field_key_to_field[$field_key])) {
                [$field, $lang] = $this->field_key_to_field[$field_key];
            } else {
                $parts = explode('_', $field_key); // ['1234', 'en'] oppure ['1234']
                $field_id = $parts[0]; // 1234
                $lang = @$parts[1]; // 'it' oppure null
                $field = Cache::idToField($field_id);
                $this->field_key_to_field[$field_key] = [$field, $lang];
            }
            if (!$field->is_multiple) {
                $values = [$values];
            }
            foreach ($values as $value) {
                $data = $field->typeClass()::jsonToValueColumns($value);
                $insert[] = [
                    'thing_id' => $thing->id,
                    'field_id' => $field->id,
                    'lang'     => $lang,
                ] + $data + [
                    'value_txt'   => null,
                    'value_token' => null,
                    'value_real0' => null,
                    'value_real1' => null,
                    'value_real2' => null,
                ];
            }
        }
    }

    function importRelations()
    {
        $things = $this->changes['Thing']->items;

        $this->comment('Importing relations...');
        $chunks = $things->chunk(100);
        $bar = $this->createBar(count($chunks));
        foreach ($chunks as $chunk_i => $chunk) {
            $relations_op = collect([]);
            foreach ($chunk as $thing) {
                foreach ($thing->rel_ids as $field_id => $related_ids) {
                    foreach ($related_ids as $thing_to_id) {
                        $relations_op->push([
                            'thing_from_id' => $thing->id,
                            'field_id'      => $field_id,
                            'thing_to_id'   => $thing_to_id,
                        ]);
                    }
                }
            }

            // Delete and reinsert relations
            Models\Relation::whereIn('thing_from_id', $chunk->pluck('id'))->delete();
            foreach ($relations_op->chunk(5000) as $insert_chunk) {
                Models\Relation::insert($insert_chunk->all());
            }

            // Advance bar
            $bar->advance();
        }
        $bar->finish();
        echo "\n";
    }

    function computeChanges($model, $objects, $check_columns)
    {
        $model_name = collect(explode('\\', $model))->last();
        $existing_objects = $model::withoutGlobalScopes()->get();
        [$objects_to_delete, $objects_to_add, $objects_to_update] = $this->compareCollections($model, $check_columns, $objects, $existing_objects);

        if (count($objects_to_add) > 0) {
            $this->comment(count($objects_to_add) . " new {$model_name}s will be added.");
        }
        $is_res = $model == Models\Resource::class;
        $is_field = $model == Models\Field::class;
        if ($is_res || $is_field) {
            if (count($objects_to_delete)) {
                $this->danger = true;
                $this->error("The following {$model_name}s will be deleted:");
                foreach ($objects_to_delete as $obj) {
                    if ($is_res) {
                        $this->comment("- $obj->name");
                    } else {
                        $this->comment("- {$obj->resource->name} -> $obj->name");
                    }
                }
            }
            if (count($objects_to_update)) {
                $this->danger = true;
                $this->error("The following {$model_name}s have changed:");
                foreach ($objects_to_update as $update) {
                    $obj = $update->existing;
                    if ($is_res) {
                        $this->comment("- $obj->name");
                    } else {
                        $this->comment("- {$obj->resource->name} -> $obj->name");
                    }

                    foreach ($check_columns as $col) {
                        if ($update->existing->$col != $update->new->$col) {
                            $this->error("  [$col] => (from {$update->existing->$col} to {$update->new->$col})");
                        }
                    }
                }
            }
        } else {
            if (count($objects_to_delete) > 0) {
                $this->comment(count($objects_to_delete) . " {$model_name}s will be removed.");
            }
        }

        $this->changes[$model_name] = (object) [
            'items'  => $objects,
            'del'    => $objects_to_delete,
            'add'    => $objects_to_add,
            'update' => $objects_to_update,
        ];
    }

    function compareCollections($model, array $check_columns, $new_items, $existing_items)
    {
        [$to_delete, $to_add, $to_update] = [[], [], []];
        $existing_items = $existing_items->keyBy('id');
        $new_items = $new_items->keyBy('id');
        foreach ($existing_items as $id => $existing_item) {
            if (!isset($new_items[$id])) {
                $to_delete[] = $existing_item;
            } else {
                $new_item = $new_items[$id];
                foreach ($check_columns as $col) {
                    if ($existing_item->$col != $new_item->$col) {
                        $to_update[] = (object) [
                            'existing' => $existing_item,
                            'new'      => $new_item,
                        ];
                        break;
                    }
                }
            }
        }

        foreach ($new_items as $id => $item) {
            if (!isset($existing_items[$id])) {
                $to_add[] = $item;
            }
        }
        return [$to_delete, $to_add, $to_update];
    }
}
