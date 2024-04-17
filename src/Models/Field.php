<?php

namespace OnPage\Models;

use Illuminate\Database\Eloquent\Builder;

class Field extends OpModel
{
    protected $table = 'op_fields';
    const FIELD_TYPES = [
        'string'   => StringType::class,
        'text'     => StringType::class,
        'paragraph' => StringType::class,
        'markdown' => StringType::class,
        'html'     => StringType::class,
        'url'      => StringType::class,
        'int'      => NumberType::class,
        'real'     => NumberType::class,
        'dim1'     => NumberType::class,
        'dim2'     => DimType::class,
        'dim3'     => DimType::class,
        'volume'   => NumberType::class,
        'weight'   => NumberType::class,
        'price'    => NumberType::class,
        'bool'     => BoolType::class,
        'date'     => StringType::class,
        'json'     => StringType::class,
        'editorjs' => StringType::class,
        'file'     => FileType::class,
        'image'    => FileType::class,
    ];

    function scopeSorted($q)
    {
        return $q->orderBy('order')->orderBy('id');
    }

    function typeClass()
    {
        return self::FIELD_TYPES[$this->type];
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }

    public function values()
    {
        return $this->hasMany(Value::class, 'thing_id');
    }

    public function things()
    {
        return $this->hasOneThrough(Thing::class, Value::class);
    }

    public function rel_res()
    {
        return $this->belongsTo(Resource::class, 'rel_res_id');
    }

    function getOptsAttribute($opts): object
    {
        return (object) json_decode($opts ?? '{}');
    }
    function setOptsAttribute($opts)
    {
        if (!is_array($opts) && !is_object($opts)) {
            throw new \Exception("Invalid opts");
        }
        $this->attributes['opts'] = json_encode($opts);
    }

    function getLabelsAttribute($labels): array
    {
        return json_decode($labels ?? '{}', true);
    }

    function setLabelsAttribute($labels)
    {
        if (!is_array($labels) && !is_object($labels)) {
            throw new \Exception("Invalid labels");
        }
        $this->attributes['labels'] = json_encode($labels);
    }

    function setDescriptionsAttribute($descriptions)
    {
        if (!is_array($descriptions) && !is_object($descriptions)) {
            throw new \Exception("Invalid descriptions");
        }
        $this->attributes['descriptions'] = json_encode($descriptions);
    }

    function getDescriptionsAttribute($description): array
    {
        return json_decode($descriptions ?? '{}', true);
    }

    function getUnit(): ?string
    {
        return $this->unit ?? null;
    }
}

interface FieldType
{
    static function jsonToValueColumns($value): array;

    static function getValue(Value $value);

    static function filter($q, $op, $value, string $subfield = null);

    static function filterIn($q, $values, string $subfield = null);
}

class StringType implements FieldType
{
    static function jsonToValueColumns($value): array
    {
        return [
            'value_txt' => $value,
        ];
    }

    static function getValue(Value $value)
    {
        return $value->value_txt;
    }

    static function filter($q, $op, $value, string $subfield = null)
    {
        return $q->where('value_txt', $op, $value);
    }

    static function filterIn($q, $values, string $subfield = null)
    {
        return $q->whereIn('value_txt', $values);
    }
}
class NumberType implements FieldType
{
    static function jsonToValueColumns($value): array
    {
        return [
            'value_real0' => $value,
        ];
    }

    static function getValue(Value $value)
    {
        return $value->value_real0;
    }

    static function filter($q, $op, $value, string $subfield = null)
    {
        $q->where('value_real0', $op, (float) $value);
    }

    static function filterIn($q, $values, string $subfield = null)
    {
        return $q->whereIn('value_real0', $values);
    }
}
class BoolType extends NumberType
{
    static function getValue(Value $value)
    {
        return (bool) $value->value_real0;
    }

    static function filter($q, $op, $value, string $subfield = null)
    {
        $q->where('value_real0', $op, (bool) $value);
    }

    static function filterIn($q, $values, string $subfield = null)
    {
        return $q->whereIn('value_real0', $values);
    }
}
class DimType implements FieldType
{
    static function jsonToValueColumns($value): array
    {
        return [
            'value_real0' => @$value[0],
            'value_real1' => @$value[1],
            'value_real2' => @$value[2],
        ];
    }

    static function getValue(Value $value)
    {
        return [
            $value->value_real0,
            $value->value_real1,
            $value->value_real2,
        ];
    }

    static function filter($q, $op, $value, string $subfield = null)
    {
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

    static function filterIn($q, $values, string $subfield = null)
    {
        switch ($subfield) {
            case 'x':
            case '0':
                $q->whereIn('value_real0', $values);
                break;
            case 'y':
            case '1':
                $q->whereIn('value_real1', $values);
                break;
            case 'z':
            case '2':
                $q->whereIn('value_real2', $values);
                break;
        }
    }
}
class FileType implements FieldType
{
    static function jsonToValueColumns($value): array
    {
        return [
            'value_txt'   => $value->name,
            'value_token' => $value->token,
        ];
    }

    static function getValue(Value $value)
    {
        return new \OnPage\LocalFile([
            'name'  => $value->value_txt,
            'token' => $value->value_token,
        ]);
    }

    static function filter($q, $op, $value, string $subfield = null)
    {
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

    static function filterIn($q, $values, string $subfield = null)
    {
        if (!$subfield) {
            $subfield = 'name';
        }

        switch ($subfield) {
            case 'name':
                $q->whereIn('value_txt', $values);
                break;
            case 'token':
                $q->whereIn('value_token', $values);
                break;
        }
    }
}
