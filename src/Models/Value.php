<?php

namespace OnPage\Models;


class Value extends OpModel
{
    protected $table = 'op_values'; 
    public function thing() {
        return $this->belongsTo(Thing::class, 'thing_id');
    }
    public function field() {
        return $this->belongsTo(Field::class, 'field_id');
    }
    public function is_multiple() {
        return $this->field->is_multiple;
    }
}