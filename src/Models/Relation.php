<?php

namespace OnPage\Models;

class Relation extends OpModel {
    protected $table = 'op_relations';
 
    public function from() {
        return $this->hasOne(Thing::class, 'thing_from_id');
    }

    public function to() {
        return $this->hasOne(Thing::class, 'thing_to_id');
    }

    public function field() {
        return $this->hasOne(Field::class, 'field_id');
    }
}
