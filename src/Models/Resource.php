<?php

namespace OnPage\Models;

class Resource extends OpModel {
    protected $table = 'op_resources';
    private static $resources = [];
    private static $id_to_field = [];
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

    public function fieldFastFromName(string $field_name) : ? Field {
        return \OnPage\Cache::nameToField($this->id, $field_name);
    }

    static function findFast(int $id) : ? Resource {
        return \OnPage\Cache::idToResource($id);
    }
}
