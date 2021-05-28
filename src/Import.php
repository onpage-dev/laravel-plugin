<?php

namespace OnPage;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Import extends Command {
    protected $signature = 'onpage:import {snapshot_file?} {--force} {--anyway}';
    protected $danger = false;
    protected $description = 'Import data from On Page';
    private $current_token;
    private $snapshot;
    private $changes = [];
    private $field_key_to_field = [];

    function getLastToken() : ? string {
        if (!Storage::disk('local')->exists('snapshots/last_token.txt')) {
            return null;
        }
        return Storage::disk('local')->get('snapshots/last_token.txt');
    }

    function finalizeImport() {
        Storage::disk('local')->put('snapshots/last_token.txt', $this->current_token);
        Storage::disk('local')->put("snapshots/$this->current_token", json_encode($this->snapshot));
    }

    function createBar($count) {
        $bar = $this->output->createProgressBar($count);
        $bar->setBarWidth(1000);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        return $bar;
    }

    public function handle() {
        $this->loadSnapshot();
        if ($this->getLastToken() == $this->current_token && !$this->option('anyway')) {
            $this->comment("No updates found, use --anyway to re-import the last snapshot available");
            return null;
        }
        
        $this->comment("Computing changes...");
        $this->computeAllChanges();
        if (!$this->option('force') && $this->danger) {
            $confirm = $this->ask('Do you want to proceed? (y/N)');
            if (!in_array(strtolower($confirm), ['y', 'yes'])) {
                return null;
            }
        }
        
        $this->comment("Importing project {$this->snapshot->label}");
        $this->importSchema(Models\Resource::class, [
            'schema_id',
            'name',
            'label',
        ]);
        $this->importSchema(Models\Field::class, [
            'resource_id',
            'name',
            'type',
            'label',
            'is_multiple',
            'is_translatable',
            'rel_res_id',
        ]);

        // Refresh resource cache
        Cache::refresh();

        $this->generateModels();

        $this->importThings();
        $this->importRelations();

        $this->finalizeImport();
        $this->comment("Snapshot saved.");
    }

    function generateModels() {
        foreach ($this->snapshot->resources as $res) {
            generate_model_file($res);
        }
    }

    function computeAllChanges() {
        $resources = collect($this->snapshot->resources);
        $this->computeChanges(
            Models\Resource::class,
            $resources,
            [
                'name',
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
            [ ]
        );
    }

    function loadSnapshot() {
        $snapshot_file = $this->argument('snapshot_file');
        if (!$snapshot_file) {
            $company = config('onpage.company');
            $token = config('onpage.token');
            $this->comment('Getting snapshot info...');
            $info = curl_get("https://$company.onpage.it/api/view/$token/dist", function () {
                throw new \Exception("Unable to get snapshot information, please check the token and company name is correct");
            });
            $this->current_token = $info->token;

            $this->comment('Downloading snapshot...');
            $this->snapshot = curl_get("https://$company.onpage.it/api/storage/{$this->current_token}", function () {
                throw new \Exception("Unable to get snapshot information, please check the token and company name is correct");
            });
        } else {
            $this->comment("Loading snapshot...");
            $this->current_token = Storage::get($snapshot_file);
            $this->snapshot = json_decode(Storage::disk('local')->get($snapshot_file));
        }
    }

    function importSchema(string $model, array $fields) {
        $model_name = collect(explode('\\', $model))->last();
        $changes = $this->changes[$model_name];

        $this->comment("Importing {$model_name}s...");
        $bar = $this->createBar($changes->items->count());
        foreach ($changes->items as $item) {
            $model::withoutGlobalScopes()->updateOrCreate([
                'id' => $item->id,
            ], collect($item)->only($fields)->all());
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

    function importThings() {
        $insert = [];
        $flush = function () use (&$insert) {
            // echo "tids...\n";
            $tids = collect($insert)->pluck('thing_id')->unique();
            // echo "deleting...\n";
            Models\Value::whereIn('thing_id', $tids)->delete();
            foreach (array_chunk($insert, 100) as $ins) {
                // echo "insert...\n";
                Models\Value::insert($ins);
            }
            $insert = [];
        };

        $this->comment("Importing things...");
        $changes = $this->changes['Thing'];
        $existing_tids = Models\Thing::pluck('resource_id', 'id');
        $bar = $this->createBar($changes->items->count());
        foreach ($changes->items as $thing) {
            if (!isset($existing_tids[$thing->id])) {
                Models\Thing::create(collect($thing)->only([
                    'id',
                    'resource_id',
                ])->all());
                $existing_tids[$thing->id] = $thing->resource_id;
            }

            $this->computeThingValues($thing, $insert);
            $flush();

            $bar->advance();
        }
        $flush();
        $bar->finish();
        echo "\n";

        if (count($changes->del)) {
            $this->comment('Deleting old things...');
            foreach (array_chunk($changes->del, 1000) as $chunk) {
                $ids = collect($chunk)->pluck('id');
                Models\Thing::whereIn('id', $ids)->delete();
            }
        }
    }

    function computeThingValues(object $thing, array &$insert) {
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

    function importRelations() {
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
            foreach ($relations_op->chunk(500) as $insert_chunk) {
                Models\Relation::insert($insert_chunk->all());
            }

            // Advance bar
            $bar->advance();
        }
        $bar->finish();
        echo "\n";
    }

    function computeChanges($model, $objects, $check_columns) {
        $model_name = collect(explode('\\', $model))->last();
        $existing_objects = $model::withoutGlobalScopes()->get();
        [$objects_to_delete,$objects_to_add,$objects_to_update] = $this->compareCollections($model, $check_columns, $objects, $existing_objects);
        
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

    function compareCollections($model, array $check_columns, $new_items, $existing_items) {
        [$to_delete,$to_add,$to_update] = [[], [], []];
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
