<?php

namespace OnPage;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Import extends Command
{
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
    public function handle()
    {
        $snapshot_file = $this->argument('snapshot_file');
        $this->comment('Importing data from snapshot...');
        if(!$snapshot_file) {        
            $company = env('ONPAGE_COMPANY');
            $token = env('ONPAGE_TOKEN');
            $url = "https://$company.onpage.it/api/view/$token/dist";
            $info = \json_decode(\file_get_contents($url));
            $fileurl = "https://$company.onpage.it/api/storage/$info->token";
            echo $fileurl . "\n";
            $json=\file_get_contents($fileurl);
            $snapshot = json_decode($json);
            $filename="snapshots/" . date("Y_m_d_His") . "_snapshot.json";
            Storage::disk('local')->put($filename,$json);
            $schema_id=$snapshot->id;
            print_r( "Label:" . $snapshot->label ."\n");
            echo("Id:" . $schema_id . "\n");
            echo("Snapshot saved at " . "storage/app/" . $filename . "\n\n");
        } else {
            $snapshot=\json_decode(Storage::get($snapshot_file));
        }

        $resources=collect($snapshot->resources);
        [$resources_op,$res_to_delete,$res_to_add,$res_to_update]=
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

        $fields=$resources->pluck('fields')->collapse();
        [$fields_op,$fields_to_delete,$fields_to_add,$fields_to_update]=
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

        $things=$resources->pluck('data')->collapse();
        [$things_op,$things_to_delete,$things_to_add,$things_to_update]=
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
        if(!$force &&   $this->danger){
            $confirm = $this->ask('Do you want to proceed? (y/N)');
            if($confirm != 'y' && $confirm != 'Y' && $confirm != 'yes' && $confirm != 'YES'){
                return null;
            }
        }


        $this->comment('Uploading database...');

        echo "Resources:\n";
        $bar = $this->output->createProgressBar(count($resources_op));
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        Models\Resource::upsert($resources_op->all(), 'id');
        $res_to_delete_ids=collect($res_to_delete)->pluck('id');
        Models\Resource::whereIn('id',$res_to_delete_ids)->delete();
        $bar->finish();

        echo "\nFields:\n";
        $bar = $this->output->createProgressBar(count($fields_op));
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%'); 
        $bar->start(); 
        Models\Field::upsert($fields_op->all(), 'id');
        $fields_to_delete_ids=collect($fields_to_delete)->pluck('id');
        Models\Field::whereIn('id',$fields_to_delete_ids)->delete();
        $bar->finish();

        echo "\nThings:\n";
        $chunks=array_chunk($things_op->all() ,1000);
        
        $bar = $this->output->createProgressBar(count($chunks));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();  
        
        foreach($chunks as $chunk_i => $chunk) {            
            Models\Thing::upsert($chunk, 'id');
            $chunk_ids=collect($chunk)->pluck('id')->all();
            $things_to_delete_ids=collect($things_to_delete)->pluck('id')->all();
            Models\Thing::whereIn('id',array_intersect($things_to_delete_ids,$chunk_ids))->delete();
        }
        
        
        $bar->finish();

        echo "\nRelations\n";
        $things_relations = $things->map(function ($value) {
            return collect($value)
                ->only(['id','rel_ids']);
        })
        ->pluck('rel_ids','id');
        $thing_ids=$things_relations->keys(); 
        $chunks=array_chunk($thing_ids->all() ,1000);
        $bar = $this->output->createProgressBar(count($chunks));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();      
        
        foreach($chunks as $chunk_i => $chunk) {
            $relations_op=collect([]);
            foreach($chunk as $thing_id) {
                $field_values=collect($things_relations[$thing_id]);
                $field_keys=$field_values->keys();
                foreach($field_keys as $field_key) {
                    $relations_field=$field_values[$field_key];
                    foreach($relations_field as $thing_to_id) {
                        $relations_op->push([
                            'thing_from_id' => $thing_id,
                            'field_id' => $field_key,
                            'thing_to_id' => $thing_to_id,    
                        ]);
                    }
                }
            }
            Models\Relation::whereIn('thing_from_id',$chunk)->delete();
            foreach (array_chunk($relations_op->all(), 500) as $insert_chunk) {
                Models\Relation::insert($insert_chunk);
            }
        }
        $bar->finish();

        echo "\nValues\n";
        $things_fields = $things->map(function ($value) {
            return collect($value)
                ->only(['id','fields']);
        })
        ->pluck('fields','id');
        $thing_ids=$things_fields->keys();        
        $chunks=array_chunk($thing_ids->all() ,100);
        $bar = $this->output->createProgressBar(count($chunks));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach($chunks as $chunk_i => $chunk) {
            $bar->advance();
            $values_op=collect([]);
            foreach($chunk as $thing_id) {
                $field_values=collect($things_fields[$thing_id]);
                $field_keys=$field_values->keys();
                foreach($field_keys as $field_key) {
                    $parts = explode('_', $field_key); // ['1234', 'en'] oppure ['1234']
                    $field_id = $parts[0]; // 1234
                    $lang = @$parts[1]; // 'it' oppure null
                    $values=$field_values[$field_key];
                    $field = Models\Field::find($field_id);
                    if (!$field->is_multiple) {
                        $values = [$values];
                    }
                    foreach ($values as $value) {
                        // value can be SCALAR, FILE, DIM2, DIM3
                        $data = null;
                        switch ($field->type) {
                            case 'file':
                            case 'image':
                                $data = [
                                    'value_txt' => $value->name,
                                    'value_token' => $value->token,
                                ];
                                break;
                            case 'dim2':
                            case 'dim3':
                                $data = [
                                    'value_real0' => @$value[0],
                                    'value_real1' => @$value[1],
                                    'value_real2' => @$value[2],
                                ];
                                break;
                            case 'int':
                            case 'real':
                                $data = [
                                    'value_real0' => $value,
                                ];
                                break;
                            default:
                                $data = [
                                    'value_txt' => $value,
                                ];
                        }
                        $values_op->push([
                            'thing_id' => $thing_id,
                            'field_id' => $field_id,
                            'lang' => $lang,
                        ] + $data + [
                            'value_txt' => null,
                            'value_token' => null,
                            'value_real0' => null,
                            'value_real1' => null,
                            'value_real2' => null,
                        ]);
                    }
                }
            }
            Models\Value::whereIn('thing_id',$chunk)->delete();
            foreach (array_chunk($values_op->all(), 500) as $insert_chunk) {
                Models\Value::insert($insert_chunk);
            }
        }
        $bar->finish();
        
        echo "\nModels\n";
        $bar = $this->output->createProgressBar(count($resources_op));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach($resources as $resource) {
            $this->op_gen_model($resource);
            $bar->advance();
        }
        $bar->finish();
        echo "\n";  

        Models\Resource::cacheResources();
    }

    function import($name, $objects, $keys, $pluck){
        $objects_op = $objects->map(function ($object) use ($keys) {
            return collect($object)
                ->only($keys)
                ->all();
        });

        $model='OnPage\\Models\\' . $name;
        $objects_old=$model::all();
        $objects_old = $objects_old->map(function ($object) use ($keys) {
            return collect($object)
                ->only($keys)
                ->all();
        });
        [$objects_to_delete,$objects_to_add,$objects_to_update]=$this->compareCollections($objects_old,$objects_op);
        


        if(count($objects_to_add)>0) {
            echo count($objects_to_add) . " new " . $name . "s will be added.";
            //echo collect($objects_to_add)->pluck($pluck);
            echo "\n";
        }
        
        if($objects_to_delete || $objects_to_update){
            $this->error("-- DANGER --");
            if($objects_to_delete) {
                echo "The following " . $name . "s will be deleted: ";
                echo collect($objects_to_delete)->pluck($pluck);
                echo "\n";
            }
            if($objects_to_update) {
                echo "The following " . $name . "s have changed: \033[0m\n";
                foreach($objects_to_update as $obj){
                    foreach($obj['old'] as $key => $value) {
                        echo "[" . $key . "] " . "=> ";
                        if( $value == $obj['new'][$key]) {
                            echo $value;
                        } else {
                            echo "(from ";
                            echo "\033[31m";
                            echo $value;
                            echo "\033[0m";
                            echo " to ";
                            echo "\033[32m";
                            if ($obj['new'][$key] == ""){
                                echo "NULL";
                            } else {
                                echo $obj['new'][$key];
                            }
                            echo "\033[0m)";
                        }
                        echo "\n";
                    }

                }
            }
            echo("\033[0m");
        }
        
       
        return [$objects_op,$objects_to_delete,$objects_to_add,$objects_to_update];        
    }

    function compareCollections($collection1, $collection2) {
        [$to_delete,$to_add,$to_update]=[[],[],[]];
        $collection1 = $collection1->keyBy('id');
        $collection2 = $collection2->keyBy('id');
        foreach ($collection1 as $id => $item) {
            if (!isset($collection2[$id])) {
                $to_delete[]= $item;
                $this->danger=true;
            } else {
                if ($collection2[$id] != $item) {
                    $to_update[]=[
                        'old' => $item,
                        'new' => $collection2[$id]
                    ];
                    $this->danger=true;
                }
            }
        }
        
        foreach ($collection2 as $id => $item) {
            if (!isset($collection1[$id])) {
                $to_add[]= $item;
            }
        }
        return [$to_delete,$to_add,$to_update];
    }

    function op_gen_model(object $res) {
        $camel_name = $this->op_snake_to_camel($res->name); 
        $code = "<?php\nnamespace OnPage\\Op; \n";
        $code.= "class $camel_name extends \OnPage\Models\Thing {\n";
        $code.= "  protected \$table = 'things'; \n";
        $code.= "  public static function boot() {
        parent::boot();
        self::addGlobalScope('resource', function(\$q) {
            \$q->whereResource_id($res->id);
        });
        }\n";
        foreach ($res->fields as $f) {
            if ($f->type == 'relation') {
                $rel_class=Models\Resource::find($f->rel_res_id)->name;
                $rel_class = $this->op_snake_to_camel($rel_class);
                $code.= "  function $f->name() {\n";
                $code.= "    return \$this->belongsToMany($rel_class::class, \OnPage\Models\Relation::class,'thing_from_id','thing_to_id');\n";
                $code.= "  }\n";
            }
        }
        $code.= "}\n";
        $file = __DIR__."/../src/Op/$camel_name.php";
        file_put_contents($file, $code);
    }
  
    function op_snake_to_camel($str) {
      $str = explode('_', $str);
      $ret = '';
      foreach ($str as $s) {
        $ret.= strtoupper(@$s[0]).substr($s, 1);
      }
      return $ret;
    }
}

