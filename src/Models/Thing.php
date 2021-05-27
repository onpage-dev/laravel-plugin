<?php
namespace OnPage\Models;
use Illuminate\Database\Eloquent\Builder;

class Thing extends OpModel {
    protected $table = 'op_things'; 
    private $value_map = null;
    
    protected static function booted() {
        static::addGlobalScope('loaded', function (Builder $builder) {
            $builder->loaded();
        });
    }

    function resource() {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }

    function values() {
        return $this->hasMany(Value::class, 'thing_id');
    }

    function fields() {
        return $this->belongsToMany(Field::class, Value::class, 'thing_id');
    }

    function relations() {
        return $this->hasMany(Relation::class, 'thing_from_id');
    }

    function relatedThings() {
        return $this->belongsToMany(Thing::class, Relation::class, 'thing_from_id', 'thing_to_id');
    }

    function getValues(string $lang = null) : array {
        $values = ['id' => $this->id];
        foreach ($this->fields as $field) {
            $values[$field->name] = $this->val($field->name, $lang);
        }
        return $values;
    }

    function getLabelAttribute() {
        $fields = $this->fields;
        $f = $fields->where('type', 'string')->first();
        return $this->val($f->name);
    }

    function getResource() : Resource {
        return Resource::findFast($this->resource_id);
    }

    function valuesFast(string $field_id, string $lang = null) : array {
        if (!$this->value_map) {
            $this->value_map = [];
            foreach ($this->values as $value) {
                $this->value_map["$value->field_id-$value->lang"][] = $value;
            }
        }

        if (isset($this->value_map["$field_id-$lang"])) {
            return $this->value_map["$field_id-$lang"];
        } else {
            return [];
        }
    }

    function scopeLoaded(Builder $q) {
        $q->with([
            'values'
        ]);
    }

    function scopeUnloaded(Builder $q) {
        $q->withoutGlobalScope('loaded');
    }

    function val(string $field_name, string $lang = null) {
        $field = $this->getResource()->fieldFastFromName($field_name);
        if (!$field) {
            throw new \Exception("Cannot find field {$field_name}");
        }
        if ($field->is_translatable && !$lang) {
            $lang = \OnPage\op_lang();
        }
        if (!$field->is_translatable) {
            $lang = null;
        }
        $values = $this->valuesFast($field->id, $lang);
        $ret = collect();
        foreach ($values as $value) {
            $ret->push($field->typeClass()::getValue($value));
        }
        // $ret = ['description']
        if ($field->is_multiple) {
            return $ret; // collect(['description'])
        } else {
            return $ret->first(); // 'description'
        }
    }

    private function fieldExplode($field_name) : array {
        $res = $this->getResource();
        // Remove .lang
        $parts = explode('.', $field_name);
        $field_name = $parts[0];
        $lang = isset($parts[1]) ? $parts[1] : \OnPage\op_lang();
        // Remove :subtype from fieldname
        $parts = explode(':', $field_name);
        $field_name = $parts[0];
        $subfield = isset($parts[1]) ? $parts[1] : null;
        // Convert field name to Field::class
        $field = $res->fieldFastFromName($field_name);
        if (!$field->is_translatable) $lang = null;
        return [$field,$subfield,$lang];
    }

    function scopeWhereField($query, string $field_name, $op, $value = null) {
        if (is_null($value)) { $value = $op; $op = '='; }
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);
        $query->whereHas('values', function ($q) use ($field, $lang, $op, $value, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filter($q, $op, $value, $subfield);
        });      
    }

    function scopeWhereNotField($query, string $field_name, $op, $value = null) {
        if (is_null($value)) { $value = $op; $op = '='; }
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);
        $query->whereDoesntHave('values', function ($q) use ($field, $lang, $op, $value, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filter($q, $op, $value, $subfield);
        });      
    }
  
    function scopeOrWhereField($query, string $field_name, $op, $value) {
        if (is_null($value)) { $value = $op; $op = '='; }
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);
        $query->orWhereHas('values', function ($q) use ($field, $lang, $op, $value, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filter($q, $op, $value, $subfield);
        });  
    }

    function scopeWhereFieldIn($query, string $field_name, $values) {
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);        
        $query->whereHas('values', function ($q) use ($field, $lang, $values, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filterIn($q, $values, $subfield);
        });   
    }

    function scopeWhereFieldNotIn($query, string $field_name, array $values){
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);
        $query->whereDoesntHave('values', function ($q) use ($field, $lang, $values, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filterIn($q, $values, $subfield);
        });   
    }

    function scopeOrWhereFieldIn($query, string $field_name, array $values){
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);
        $query->orWhereHas('values', function ($q) use ($field, $lang, $values, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filterIn($q, $values, $subfield);
        });   
    }

    function scopeOrWhereFieldNotIn($query, string $field_name, array $values){
        [$field,$subfield,$lang]=$this->fieldExplode($field_name);
        $query->orWhereDoesntHave('values', function ($q) use ($field, $lang, $values, $subfield) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            return $field->typeClass()::filterIn($q, $values, $subfield);
        });   
    }

}
