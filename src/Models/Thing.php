<?php

namespace OnPage\Models;


class Thing extends Model
{
    public function resource() {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }
    public function values() {
        return $this->hasMany(Value::class, 'thing_id');
    }
    public function fields() {
        return $this->hasManyThrough(Field::class, Value::class);
    }
}



