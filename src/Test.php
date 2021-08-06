<?php

namespace OnPage;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Test extends Command
{
    protected $signature = 'onpage:test';
    protected $description = 'Test On Page functions';
    function handle()
    {

        $res = resource("xxxxxx");
        if (!is_null($res)) {
            throw new \Exception("Resource should be null");
        }


        $resources = resources();
        foreach ($resources as $res) {
            echo "- Testing res [$res->name]\n";

            // Test with name
            $res2 = resource($res->name);
            if (is_null($res2)) {
                throw new \Exception("Resource should not be null");
            }
            if ($res2->id != $res->id) {
                throw new \Exception("Resource id does not match");
            }

            // Test with id
            $res2 = resource($res->id);
            if (is_null($res2)) {
                throw new \Exception("Resource should not be null");
            }
            if ($res2->id != $res->id) {
                throw new \Exception("Resource id does not match");
            }

            // Test resource
            if (!is_string($res->label)) {
                throw new \Exception("Res label should be a string");
            }
            if (!is_array($res->labels)) {
                throw new \Exception("Res labels should be an array");
            }


            // Test fields
            foreach ($res->fields as $field) {

                echo @"  - {$field->label} [$field->type] {$field->getUnit()}\n";

                // Test with name
                $field2 = $res->field($field->name);
                if (is_null($field2)) {
                    throw new \Exception("Field should not be null");
                }
                if ($field2->id != $field->id) {
                    throw new \Exception("Field id does not match");
                }

                // Test with id
                $field2 = $res->field($field->id);
                if (is_null($field2)) {
                    throw new \Exception("Field should not be null");
                }
                if ($field2->id != $field->id) {
                    throw new \Exception("Field id does not match");
                }

                // Test field properties
                if (!is_string($field->type)) {
                    throw new \Exception("Field type should be a string");
                }
                if (!is_string($field->label)) {
                    throw new \Exception("Field label should be a string");
                }
                if (!is_array($field->labels)) {
                    throw new \Exception("Field labels should be an array");
                }
                if (!is_object($field->opts)) {
                    throw new \Exception("Field opts should be an object");
                }
            }
        }
    }
}
