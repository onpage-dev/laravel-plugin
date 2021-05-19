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
        print_r("ID:" . $schema_id . "\n");
        print_r( "LABEL:" . $snapshot->label . "\n");
        echo "**************************************\n";
        echo "Importando Raccolte...\n";
        
        $resources=collect($snapshot->resources);
        $resources_op = $resources->map(function ($resource) {
            return collect($resource)
                ->only(['id', 'label', 'schema_id'])
                ->all();
        });
        Models\Resource::insert($resources_op->all());
        echo "Raccolte importate:" . $resources_op->count() . ".\n";
        print_r($resources_op->pluck('label') . "\n");
        echo "**************************************\n";
        echo "Importando Campi...\n";
        //print_r($resources_op);
        //print_r("IDS RESOURCES:" . $resources_op->pluck('id') . "\n");
        
        
        $fields=$resources->pluck('fields')->collapse();
        $fields_op = $fields->map(function ($field) {
            return collect($field)
                ->only(['id','name','resource_id','type','is_multiple','is_translatable', 'label'])
                ->all();
        });
        Models\Field::insert($fields_op->all());
        echo "Campi importati:". $fields_op->count() . ".\n";
        echo "**************************************\n";
        echo "Importando Things...\n";

        $things=$resources->pluck('data')->collapse();
        $things_op = $things->map(function ($thing) {
            return collect($thing)
                ->only(['id', 'resource_id'])
                ->all();
        });
        Models\Thing::insert($things_op->all());
        echo "Things importate:" . $things_op->count() . ".\n";
        echo "**************************************\n";
        echo "Importando Valori...\n";
        echo "Potrebbe impiegare diversi minuti...\n";

        $thing_values = $things->map(function ($value) {
            return collect($value)
                ->only(['id','fields']);
        })
        ->pluck('fields','id');
       
        $keys=$thing_values->keys();
        
        foreach($keys as $key) {
            //print_r("KEYS:");
            $field_values=collect($thing_values[$key]);
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
                    Models\Value::create([
                        'thing_id' => $key,
                        'field_id' => $field_id,
                        'lang' => $lang,
                    ] + $data);
                }
            }
        }

    $ivalues=Models\Value::all()->count();
    echo "Valori importati:" . $ivalues . ".\n";
    echo "**************************************\n";
    }
}