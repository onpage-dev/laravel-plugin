<?php

namespace OnPage\Models;

class Resource extends Model
{
    public function things() {
        return $this->hasMany(Thing::class, 'resource_id');
    }
    public function fields() {
        return $this->hasMany(Field::class, 'resource_id');
    }
    public function values() {
        return $this->hasManyThrough(Value::class, Thing::class);
    }
    public function name() {
        return $this->name;
    }
}



