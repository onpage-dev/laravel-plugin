<?php

namespace OnPage;

use Illuminate\Console\Command;

class Import extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onpage:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->comment('Importing data from snapshot...');
        $token = env('ONPAGE_TOKEN');
        $url = "https://lithos.onpage.it/api/view/$token/dist";
        $info = \json_decode(\file_get_contents($url));
        $fileurl = "https://lithos.onpage.it/api/storage/$info->token";
        $snapshot = json_decode(\file_get_contents($fileurl));
        echo "$fileurl\n\n";
        $schema_id=$snapshot->id;
        print_r( "LABEL:" . $snapshot->label ." ID:" . $schema_id);
        $resources=collect($snapshot->resources);
        $resources_op = $resources->map(function ($resource) {
            return collect($resource)
                ->only(['id', 'name','label', 'schema_id'])
                ->all();
        });
        Models\Resource::upsert($resources_op->all(), 'id');
        echo "\n\nResources:";
        echo $resources_op->pluck('name');

        echo "\n\nFields\n";

        $fields=$resources->pluck('fields')->collapse();
        $fields_op = $fields->map(function ($field) {
            return collect($field)
                ->only(['id','name','resource_id','type','is_multiple','is_translatable', 'label', 'rel_res_id'])
                ->all();
        });
        $bar = $this->output->createProgressBar(count($fields_op));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();  
        Models\Field::upsert($fields_op->all(), 'id');
        $bar->finish();
        //echo "Campi importati:". $fields_op->count() . ".\n";

        echo "\n\nThings\n";
        $things=$resources->pluck('data')->collapse();
        $things_op = $things->map(function ($thing) {
            return collect($thing)
                ->only(['id', 'resource_id'])
                ->all();
        });

        $chunks=array_chunk($things_op->all() ,1000);

        $bar = $this->output->createProgressBar(count($chunks));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();  

        foreach($chunks as $chunk_i => $chunk) {
            Models\Thing::upsert($chunk, 'id');
        }
        $bar->finish();
        //echo "Things importate:" . $things_op->count() . ".\n"; 

        echo "\n\nRelations\n";
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
            Models\Relation::insert($relations_op->all());
        }
        $bar->finish();

        echo "\n\nValues\n";
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
            Models\Value::insert($values_op->all());
            
        }
        
        $bar->finish();
        $this->comment("\n\nGenerating models...");

        $bar = $this->output->createProgressBar(count($resources_op));    
        $bar->setFormat(' [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        
        foreach($resources as $resource) {
            op_gen_model($resource);
            $bar->advance();
        }

        $bar->finish();
        echo "\n";
    }
}


function op_gen_model(object $res) {
    $camel_name = op_snake_to_camel($res->name);
    //$extends = $res->is_product ? 'Post' : 'Term';
    //$extends_lc = strtolower($extends);
  
    $code = "<?php\nnamespace OnPage\\Models; \n";
    $code.= "class $camel_name extends \\OnPage\\Resource {\n";
    $code.= "  public static function boot() {
      parent::boot();
      self::addGlobalScope('opres', function(\$q) {
        \$q->whereRes($res->id);
      });
      self::addGlobalScope('oplang', function(\$q) {
        \$q->localized();
      });
      self::addGlobalScope('opmeta', function(\$q) {
        \$q->loaded();
      });
    }\n";
    $code.= "  public static function getResource() {
      return op_schema()->name_to_res['{$res->name}'];
    }\n";
  
    foreach ($res->fields as $f) {
      if ($f->type == 'relation') {
        //$rel_method = $f->rel_res->is_product ? 'posts' : 'terms';

        $rel_class=Models\Resource::find($f->rel_res_id)->name;
        //$rel_class = op_snake_to_camel($f->rel_res->name);
        //$rel_class_primary = $f->rel_res->is_product ? 'ID' : 'term_id';
        $code.= "  function $f->name() {\n";
        $code.= "    return \$this->belongsToMany($rel_class::class, \\OnPage\\Meta::class, 'id', 'meta_value', null)\n";
        $code.= "    ->wherePivot('meta_key', 'oprel_$f->name')\n";
        $code.= "    ->orderBy('meta_id');\n";
        $code.= "  }\n";
      }
    }
    $code.= "}\n";
    $file = __DIR__."/../src/Models/$camel_name.php";
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