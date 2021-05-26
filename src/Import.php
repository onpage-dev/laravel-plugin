<?php

namespace OnPage;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Import extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onpage:import {snapshot_file?} {--force}';
    protected $danger = false;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from On Page';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $snapshot_file = $this->argument('snapshot_file');
        $this->comment('Importing data from snapshot...');
        if (!$snapshot_file) {
            $company = env('ONPAGE_COMPANY');
            $token = env('ONPAGE_TOKEN');
            $url = "https://$company.onpage.it/api/view/$token/dist";
            $info = \json_decode(\file_get_contents($url));
            $fileurl = "https://$company.onpage.it/api/storage/$info->token";
            echo $fileurl . "\n";
            $json = \file_get_contents($fileurl);
            $snapshot = json_decode($json);
            $filename = "snapshots/" . date("Y_m_d_His") . "_snapshot.json";
            Storage::disk('local')->put($filename, $json);
            $schema_id = $snapshot->id;
            print_r("Label:" . $snapshot->label ."\n");
            echo("Id:" . $schema_id . "\n");
            echo("Snapshot saved at " . "storage/app/" . $filename . "\n\n");
        } else {
            $snapshot = \json_decode(Storage::get($snapshot_file));
        }

        $resources = collect($snapshot->resources);
        [$resources_op,$res_to_delete,$res_to_add,$res_to_update] =
        $this->import(
            'Resource',
            $resources,
            [
                'id',
                'name',
                'label',
                'schema_id'
            ],
            'name'
        );

        $fields = $resources->pluck('fields')->collapse();
        [$fields_op,$fields_to_delete,$fields_to_add,$fields_to_update] =
        $this->import(
            'Field',
            $fields,
            [
                'id',
                'name',
                'resource_id',
                'type',
                'is_multiple',
                'is_translatable',
                'label',
                'rel_res_id',
            ],
            [
                'name'
            ]
        );

        $things = $resources->pluck('data')->collapse();
        [$things_op,$things_to_delete,$things_to_add,$things_to_update] =
        $this->import(
            'Thing',
            $things,
            [
                'id',
                'resource_id'
            ],
            'id'
        );

        $force = $this->option('force');
        if (!$force && $this->danger) {
            $confirm = $this->ask('Do you want to proceed? (y/N)');
            if ($confirm != 'y' && $confirm != 'Y' && $confirm != 'yes' && $confirm != 'YES') {
                return null;
            }
        }

        $this->comment('Uploading database...');

        echo "Resources:\n";
        $bar = $this->output->createProgressBar(count($resources_op));
        $bar->setBarWidth(1000);
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        Models\Resource::upsert($resources_op->all(), 'id');
        $res_to_delete_ids = collect($res_to_delete)->pluck('id');
        Models\Resource::whereIn('id', $res_to_delete_ids)->delete();
        $bar->finish();

        echo "\nFields:\n";
        $bar = $this->output->createProgressBar(count($fields_op));
        $bar->setBarWidth(1000);
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        Models\Field::upsert($fields_op->all(), 'id');
        $fields_to_delete_ids = collect($fields_to_delete)->pluck('id');
        Models\Field::whereIn('id', $fields_to_delete_ids)->delete();
        $bar->finish();

        echo "\nThings:\n";
        $chunks = array_chunk($things_op->all(), 1000);
        
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->setBarWidth(1000);
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        
        foreach ($chunks as $chunk_i => $chunk) {
            Models\Thing::upsert($chunk, 'id');
            $chunk_ids = collect($chunk)->pluck('id')->all();
            $things_to_delete_ids = collect($things_to_delete)->pluck('id')->all();
            Models\Thing::whereIn('id', array_intersect($things_to_delete_ids, $chunk_ids))->delete();
        }
        
        $bar->finish();

        echo "\nRelations\n";
        $things_relations = $things->map(function ($value) {
            return collect($value)
                ->only(['id', 'rel_ids']);
        })
        ->pluck('rel_ids', 'id');
        $thing_ids = $things_relations->keys();
        $chunks = array_chunk($thing_ids->all(), 1000);
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->setBarWidth(1000);
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        
        foreach ($chunks as $chunk_i => $chunk) {
            $relations_op = collect([]);
            foreach ($chunk as $thing_id) {
                $field_values = collect($things_relations[$thing_id]);
                $field_keys = $field_values->keys();
                foreach ($field_keys as $field_key) {
                    $relations_field = $field_values[$field_key];
                    foreach ($relations_field as $thing_to_id) {
                        $relations_op->push([
                            'thing_from_id' => $thing_id,
                            'field_id'      => $field_key,
                            'thing_to_id'   => $thing_to_id,
                        ]);
                    }
                }
            }
            Models\Relation::whereIn('thing_from_id', $chunk)->delete();
            foreach (array_chunk($relations_op->all(), 500) as $insert_chunk) {
                Models\Relation::insert($insert_chunk);
            }
        }
        $bar->finish();

        echo "\nValues\n";
        $things_fields = $things->map(function ($value) {
            return collect($value)
                ->only(['id', 'fields']);
        })
        ->pluck('fields', 'id');
        $thing_ids = $things_fields->keys();
        $chunks = array_chunk($thing_ids->all(), 100);
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->setBarWidth(1000);
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($chunks as $chunk_i => $chunk) {
            
            $values_op = collect([]);
            foreach ($chunk as $thing_id) {
                $field_values = collect($things_fields[$thing_id]);
                $field_keys = $field_values->keys();
                foreach ($field_keys as $field_key) {
                    $parts = explode('_', $field_key); // ['1234', 'en'] oppure ['1234']
                    $field_id = $parts[0]; // 1234
                    $lang = @$parts[1]; // 'it' oppure null
                    $values = $field_values[$field_key];
                    $field = Models\Field::find($field_id);
                    if (!$field->is_multiple) {
                        $values = [$values];
                    }
                    foreach ($values as $value) {
                        $data = $field->typeClass()::jsonToValueColumns($value);
                        $values_op->push([
                            'thing_id' => $thing_id,
                            'field_id' => $field_id,
                            'lang'     => $lang,
                        ] + $data + [
                            'value_txt'   => null,
                            'value_token' => null,
                            'value_real0' => null,
                            'value_real1' => null,
                            'value_real2' => null,
                        ]);
                    }
                }
            }
            Models\Value::whereIn('thing_id', $chunk)->delete();
            foreach (array_chunk($values_op->all(), 500) as $insert_chunk) {
                Models\Value::insert($insert_chunk);
            }
            $bar->advance();
        }
        $bar->finish();
        
        echo "\nModels\n";
        $bar = $this->output->createProgressBar(count($resources_op));
        $bar->setBarWidth(1000);
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($resources as $resource) {
            generate_model_file($resource);
            $bar->advance();
        }
        $bar->finish();
        echo "\n";

        Models\Resource::cacheResources();
    }

    function import($name, $objects, $keys, $pluck) {
        $objects_op = $objects->map(function ($object) use ($keys) {
            return collect($object)
                ->only($keys)
                ->all();
        });

        $model = 'OnPage\\Models\\' . $name;
        $objects_old = $model::all();
        $objects_old = $objects_old->map(function ($object) use ($keys) {
            return collect($object)
                ->only($keys)
                ->all();
        });
        [$objects_to_delete,$objects_to_add,$objects_to_update] = $this->compareCollections($objects_old, $objects_op);
        
        if (count($objects_to_add) > 0) {
            echo count($objects_to_add) . " new " . $name . "s will be added.";
            echo "\n";
        }
        
        if ($name == 'Resource' || $name == 'Field') {
        if ($objects_to_delete || $objects_to_update) {
            $this->error("-- DANGER --");
            if ($objects_to_delete) {
                $this->error("The following " . $name . "s will be deleted:");
                $this->error(collect($objects_to_delete)->pluck($pluck));
            }
            if ($objects_to_update) {
                $this->error("The following " . $name . "s have changed:");
                foreach ($objects_to_update as $obj) {
                    foreach ($pluck as $pluck_key) {
                        $this->error("- " . $obj['old'][$pluck_key]);
                    }
                    foreach ($obj['old'] as $key => $value) {
                        if ($value != $obj['new'][$key]) {
                            $this->error("[" . $key . "] " . "=> (from " . $value . " to " . $obj['new'][$key] . ")");
                        }
                    }
                }
            }
        }
    }
        
        return [$objects_op, $objects_to_delete, $objects_to_add, $objects_to_update];
    }

    function compareCollections($collection1, $collection2) {
        [$to_delete,$to_add,$to_update] = [[], [], []];
        $collection1 = $collection1->keyBy('id');
        $collection2 = $collection2->keyBy('id');
        foreach ($collection1 as $id => $item) {
            if (!isset($collection2[$id])) {
                $to_delete[] = $item;
                $this->danger = true;
            } else {
                if ($collection2[$id] != $item) {
                    $to_update[] = [
                        'old' => $item,
                        'new' => $collection2[$id]
                    ];
                    $this->danger = true;
                }
            }
        }
        
        foreach ($collection2 as $id => $item) {
            if (!isset($collection1[$id])) {
                $to_add[] = $item;
            }
        }
        return [$to_delete, $to_add, $to_update];
    }
}
