<?php

namespace OnPage\Models;

class Field extends Model
{
    public function resource() {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }
    public function values() {
        return $this->hasMany(Value::class, 'thing_id');
    }
    public function things() {
        return $this->hasManyThrough(Thing::class, Value::class);
    }
}