<?php

namespace OnPage\Models;

class Resource extends OpModel
{
    static $resources = [];
    private $field_name_map = [];

    public function things() {
        return $this->hasMany(Thing::class, 'resource_id');
    }
    public function fields() {
        return $this->hasMany(Field::class, 'resource_id');
    }
    public function values() {
        return $this->hasManyThrough(Value::class, Thing::class);
    }

    public function fieldFastFromName(string $field_name) :? Field {
        if(!$this->field_name_map) {
            $this->field_name_map=[];
            foreach($this->fields as $field) {
                $this->field_name_map[$field->name] = $field;
            }
        }
        
        if (isset($this->field_name_map[$field_name])) {
            return $this->field_name_map[$field_name];
        } else {
            return null;
        }
    } 

    public static function cacheResources() {

        self::$resources = [];
        foreach (self::with('fields')->get() as $res) {
            self::$resources[$res->id] = $res;
        }
    }

    static function findFast(int $id) :? Resource {
        return self::$resources[$id];
    }
}

Resource::cacheResources();

