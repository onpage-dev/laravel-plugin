<?php

namespace OnPage\Models;


class Thing extends OpModel
{
    public function resource() {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }
    public function values() {
        return $this->hasMany(Value::class, 'thing_id');
    }
    public function fields() {
        return $this->belongsToMany(Field::class, Value::class , 'thing_id');
    }
    public function relations() {
        return $this->hasMany(Relation::class, 'thing_from_id');
    }
    public function relatives() {
        return $this->belongsToMany(Thing::class, Relation::class,'thing_from_id','thing_to_id');
    }

    
    public function getResource() {
        return $this->resource->name;
    }
    public function getFields() {
        return $this->fields->pluck('name')->all();
    }
    public function getAllFields() {
        return $this->resource->fields->pluck('name')->all();
    }
    public function getValues() {
        $values=collect([]);
        $fields=$this->fields;
        foreach($fields as $f) {
            $n=$f->name;
            $v=$this->val($n);
            $values->put($n,$v);
        }
        return $values->all();
    }
    public function getName() {
        $fields=$this->fields;
        $f=$fields->where('type','string')->first();
        return $this->val($f->name);
    }

    public function val(string $field_name, string $lang = null) {
        $field = $this->resource->fields()->where('name', $field_name)->first();
        if (!$field) return null;
        $values = $this->values()
            ->where('field_id', $field->id)
            ->where('lang', $lang)
            ->get();
        $ret = [];
        foreach ($values as $value) {
            switch ($field->type) {
                case 'file':
                case 'image':
                    $ret[] = [
                        'name' => $value->value_txt,
                        'token' => $value->value_token,
                    ];
                    break;
                case 'dim2':
                case 'dim3':
                    $ret[] = [
                        $value->value_real0,
                        $value->value_real1,
                        $value->value_real2,
                    ];
                    break;
                case 'int':
                case 'real':
                    $ret[] = $value->value_real0;
                    break;
                default:
                    $ret[] = $value->value_txt;
            }
        }
        // $ret = ['description']
        if ($field->is_multiple) {
            return $ret; // ['description']
        } else {
            return @$ret[0]; // 'description'
        }
    }

}



