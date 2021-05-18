<?php

namespace OnPage\Models;


class Value extends Model
{
    public function thing() {
        return $this->belongsTo(Thing::class, 'thing_id');
    }
    public function field() {
        return $this->belongsTo(Field::class, 'field_id');
    }
}