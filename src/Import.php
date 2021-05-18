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
        // print_r($snapshot);
        echo "$fileurl\n\n";
        print_r( "LABEL:" . $snapshot->label . "\n");
        $schema_id=$snapshot->id;
        print_r("SCHEMA_ID:" . $schema_id . "\n");
        
        $resources=collect($snapshot->resources);
        print_r( "Numero RESOURCES:" . $resources->count() . "\n");
        $resources_op = $resources->map(function ($resource) {
            return collect($resource)
                ->only(['id', 'label', 'schema_id'])
                ->all();
        });
        //print_r($resources_op);
        //print_r("IDS RESOURCES:" . $resources_op->pluck('id') . "\n");
        //print_r( "LABELS:" . $resources_op->pluck('label') . "\n");
        
        $fields=$resources->pluck('fields')->collapse();
        print_r( "Numero FIELDS:" . $fields->count() . "\n");
        $fields_op = $fields->map(function ($field) {
            return collect($field)
                ->only(['id', 'label', 'resource_id'])
                ->all();
        });
        //print_r($fields_op);
        //print_r("IDS FIELDS:" . $fields_op->pluck('id') . "\n");
        //print_r( "LABELS:" . $fields_op->pluck('label') . "\n");

        $things=$resources->pluck('data')->collapse();
        print_r( "Numero THINGS:" . $things->count() . "\n");
        $things_op = $things->map(function ($thing) {
            return collect($thing)
                ->only(['id', 'label', 'resource_id'])
                ->all();
        });
        //print_r($things_op);
        //print_r("THING FIELDS:" . $things_op->pluck('id') . "\n");
        //print_r( "LABELS:" . $things_op->pluck('label') . "\n");

        //$values=$things->pluck('fields');
        //print_r($values);

        $values = $things->map(function ($value) {
            return collect($value)
                ->only(['id','fields']);
        })
        ->pluck('fields','id');

        /* $values_op= $values->map(function ($value) {
            return collect($value)
                ->only(['id','fields'])
                ->all();
        }); */

        $values_op=collect([]);        
        $keys=$values->keys();

        foreach($keys as $key) {
            //print_r("KEYS:");
            $subvalues=collect($values[$key]);
            $field_keys=$subvalues->keys();
            foreach($field_keys as $field_key) {
/*                 echo "valueskey:";
                print_r($subvalues);
                echo "metavalue:";
                print_r($subvalues[$field_key]);
                echo "\n field_id:";
                print_r($field_key);
                echo "\n thing_id:";
                print_r($key);
                echo "\n\n"; */
                $metavalue=json_encode($subvalues[$field_key]);
                echo "\n";
                $values_op->push( ["field_id" => $field_key, "thing_id" => $key , "metavalue" => $metavalue]);
        }
    }
        //print_r($values_op); 
        print_r("Numero VALUES:" . $values_op->count() . "\n");
        echo "**************************************\n";
        echo "**************************************\n";
        echo "**************************************\n";
        Models\Resource::insert($resources_op->all());
        echo "Risorse inserite nel database\n";
        Models\Field::insert($fields_op->all());
        echo "Campi inseriti nel database\n";
        Models\Thing::insert($things_op->all());
        echo "Things inserite nel database\n";
        echo "quasi\n";
        Models\Value::insert($values_op->all());
        echo "Valori inseriti nel database\n";
        //print_r($values_op->all());
    }
}