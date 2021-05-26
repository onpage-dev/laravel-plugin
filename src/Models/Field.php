<?php

namespace OnPage\Models;

class Field extends OpModel {
    protected $table = 'op_fields'; 
    const FIELD_TYPES = [
        'string'   => StringType::class,
        'text'     => StringType::class,
        'html'     => StringType::class,
        'markdown' => StringType::class,
        'int'      => NumberType::class,
        'real'     => NumberType::class,
        'price'    => NumberType::class,
        'dim1'     => NumberType::class,
        'weight'   => NumberType::class,
        'volume'   => NumberType::class,
        'dim2'     => DimType::class,
        'dim3'     => DimType::class,
        'file'     => FileType::class,
        'image'    => FileType::class,
    ];

    function typeClass() {
        return self::FIELD_TYPES[$this->type];
    }

    public function resource() {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }

    public function values() {
        return $this->hasMany(Value::class, 'thing_id');
    }

    public function things() {
        return $this->hasOneThrough(Thing::class, Value::class);
    }

    public function rel_res() {
        return $this->belongsTo(Resource::class, 'rel_res_id');
    }
}

interface FieldType {
    static function jsonToValueColumns($value) : array;

    static function getValue(Value $value);

    static function filter($q, $op, $value, string $subfield = null);
}

class StringType implements FieldType {
    static function jsonToValueColumns($value) : array {
        return [
            'value_txt' => $value,
        ];
    }

    static function getValue(Value $value) {
        return $value->value_txt;
    }

    static function filter($q, $op, $value, string $subfield = null) {
        return $q->where('value_txt', $op, $value);
    }
}
class NumberType implements FieldType {
    static function jsonToValueColumns($value) : array {
        return [
            'value_real0' => $value,
        ];
    }

    static function getValue(Value $value) {
        return $value->value_real0;
    }

    static function filter($q, $op, $value, string $subfield = null) {
        $q->where('value_real0', $op, $value);
    }
}
class DimType implements FieldType {
    static function jsonToValueColumns($value) : array {
        return [
            'value_real0' => @$value[0],
            'value_real1' => @$value[1],
            'value_real2' => @$value[2],
        ];
    }

    static function getValue(Value $value) {
        return [
            $value->value_real0,
            $value->value_real1,
            $value->value_real2,
        ];
    }

    static function filter($q, $op, $value, string $subfield = null) {
        switch ($subfield) {
            case 'x':
            case '0':
                $q->where('value_real0', $op, $value);
            break;
            case 'y':
            case '1':
                $q->where('value_real1', $op, $value);
            break;
            case 'z':
            case '2':
                $q->where('value_real2', $op, $value);
            break;
        }
    }
}
class FileType implements FieldType {
    static function jsonToValueColumns($value) : array {
        return [
            'value_txt'   => $value->name,
            'value_token' => $value->token,
        ];
    }

    static function getValue(Value $value) {
        return new \OnPage\File([
            'name'  => $value->value_txt,
            'token' => $value->value_token,
        ]);
    }

    static function filter($q, $op, $value, string $subfield = null) {
        if (!$subfield) {
            $subfield = 'name';
        }

        switch ($subfield) {
            case 'name':
                $q->where('value_txt', $op, $value);
            break;
            case 'token':
                $q->where('value_token', $op, $value);
            break;
        }
    }
}
