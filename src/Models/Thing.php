<?php

namespace OnPage\Models;


class Thing extends OpModel
{
    private $value_map=null;

    function resource() {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }
    function values() {
        return $this->hasMany(Value::class, 'thing_id');
    }
    function fields() {
        return $this->belongsToMany(Field::class, Value::class , 'thing_id');
    }
    function relations() {
        return $this->hasMany(Relation::class, 'thing_from_id');
    }
    function relatedThings() {
        return $this->belongsToMany(Thing::class, Relation::class,'thing_from_id','thing_to_id');
    }

    function getValues(string $lang = null) : array {
        $values = [];
        foreach($this->fields as $field) {
            $values[$field->name] = $this->val($field->name, $lang);
        }
        return $values;
    }

    function getLabelAttribute() {
        $fields=$this->fields;
        $f=$fields->where('type','string')->first();
        return $this->val($f->name);
    }

    function getResAttribute() :? Resource {
        return Resource::findFast($this->resource_id);
    }

    function valuesFast(string $field_id, string $lang = null) : array {
        if(!$this->value_map) {
            $this->value_map=[];
            foreach($this->values as $value) {
                $this->value_map["$value->field_id-$value->lang"][] = $value;
            }
        }

        if (isset($this->value_map["$field_id-$lang"])) {
            return $this->value_map["$field_id-$lang"];
        } else {
            return [];
        }
    }

    function scopeLoaded($q) {
        $q->with([
            'values'
        ]);
    }

    function val(string $field_name, string $lang = null) {
        $field = $this->res->fieldFastFromName($field_name);
        if (!$field) throw new \Exception("Cannot find field {$field_name}");    
        if ($field->is_translatable && !$lang) {
            $lang = op_lang();
        }
        if (!$field->is_translatable) {
            $lang = null;
        }
        $values = $this->valuesFast($field->id, $lang);
        $ret = collect();
        foreach ($values as $value) {
            switch ($field->type) {
                case 'file':
                case 'image':
                    $ret->push(new \OnPage\File([
                        'name' => $value->value_txt,
                        'token' => $value->value_token,
                    ]));
                    break;
                case 'dim2':
                case 'dim3':
                    $ret->push([
                        $value->value_real0,
                        $value->value_real1,
                        $value->value_real2,
                    ]);
                    break;
                case 'int':
                case 'real':
                case 'price':
                    $ret->push($value->value_real0);
                    break;
                default:
                    $ret->push($value->value_txt);
            }
        }
        // $ret = ['description']
        if ($field->is_multiple) {
            return $ret; // collect(['description'])
        } else {
            return $ret->first(); // 'description'
        }
    }
}



function op_url(string $token, string $name = null) : string {
    $domain = env('ONPAGE_COMPANY');
    $url = "https://{$domain}.onpage.it/api/storage/$token";
    if ($name) {
        $url.= '?'.http_build_query([
            'name' => $name,
        ]);
    }
    return $url;
}

function op_lang(string $set = null) {
    static $current;
    if ($set) {
        $current = $set;
    } elseif(!$current) {
        $current = app()->getLocale();
    }
    return $current;
}