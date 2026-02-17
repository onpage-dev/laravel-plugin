<?php

namespace OnPage\Models;

use Illuminate\Database\Eloquent\Builder;

class Thing extends OpModel
{
    protected $table = 'op_things';
    private $value_map = null;

    protected static function booted()
    {
        static::addGlobalScope('loaded', function (Builder $builder) {
            $builder->loaded();
        });
    }

    function scopeSorted(Builder $q)
    {
        return $q->orderBy('order')->orderBy('id');
    }

    function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id', 'id');
    }

    function values()
    {
        return $this->hasMany(Value::class, 'thing_id');
    }

    function fields()
    {
        return $this->belongsToMany(Field::class, Value::class, 'thing_id');
    }

    function relations()
    {
        return $this->hasMany(Relation::class, 'thing_from_id');
    }

    function relatedThings()
    {
        return $this->belongsToMany(Thing::class, Relation::class, 'thing_from_id', 'thing_to_id');
    }

    function defaultFolder()
    {
        return $this->belongsTo(FieldFolders::class, 'default_folder_id');
    }

    function getValues(?string $lang = null): array
    {
        $values = ['id' => $this->id];
        foreach ($this->fields as $field) {
            $values[$field->name] = $this->val($field->name, $lang);
        }
        return $values;
    }

    function getLabelAttribute()
    {
        $fields = $this->fields;
        $f = $fields->where('type', 'string')->first();
        return $this->val($f->name);
    }

    function getResource(): Resource
    {
        return Resource::findFast($this->resource_id);
    }

    function valuesFast(string $field_id, ?string $lang = null): array
    {
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

    function scopeLoaded(Builder $q)
    {
        $q->with([
            'values'
        ]);
    }

    function scopeUnloaded(Builder $q)
    {
        $q->withoutGlobalScope('loaded');
    }

    function val(string $field_name, ?string $lang = null)
    {
        $field = $this->getResource()->field($field_name);
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

    private function fieldExplode($field_name): array
    {
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
        $field = $res->field($field_name);
        if (!$field->is_translatable) {
            $lang = null;
        }
        return [$field, $subfield, $lang];
    }

    private function isValueEmpty($value): bool
    {
        return $value === null || $value === '';
    }

    private function valueScope($field, $lang, ?\Closure $extra = null): \Closure
    {
        return function ($q) use ($field, $lang, $extra) {
            $q->where('field_id', $field->id);
            $q->where('lang', $lang);
            if ($extra) $extra($q);
        };
    }

    private function applyWhereField($query, string $field_name, $op, $value, bool $or, bool $not)
    {
        [$field, $subfield, $lang] = $this->fieldExplode($field_name);

        if ($op === '=' && $this->isValueEmpty($value)) {
            $method = $not ? 'whereHas' : 'whereDoesntHave';
            if ($or) $method = 'or' . ucfirst($method);
            $query->$method('values', $this->valueScope($field, $lang));
            return;
        }

        $method = $not ? 'whereDoesntHave' : 'whereHas';
        if ($or) $method = 'or' . ucfirst($method);
        $query->$method('values', $this->valueScope($field, $lang, fn($q) =>
            $field->typeClass()::filter($q, $op, $value, $subfield)
        ));
    }

    private function applyWhereFieldIn($query, string $field_name, array $values, bool $or, bool $not)
    {
        [$field, $subfield, $lang] = $this->fieldExplode($field_name);
        $nonEmpty = array_values(array_filter($values, fn($v) => !$this->isValueEmpty($v)));
        $hasEmpty = count($values) !== count($nonEmpty);

        if ($not) {
            if ($hasEmpty) {
                $method = $or ? 'orWhereHas' : 'whereHas';
                $query->$method('values', $this->valueScope($field, $lang,
                    !empty($nonEmpty) ? fn($q) => $field->typeClass()::filterNotIn($q, $nonEmpty, $subfield) : null
                ));
                return;
            }
            $method = $or ? 'orWhereDoesntHave' : 'whereDoesntHave';
            $query->$method('values', $this->valueScope($field, $lang, fn($q) =>
                $field->typeClass()::filterIn($q, $values, $subfield)
            ));
            return;
        }

        if ($hasEmpty && empty($nonEmpty)) {
            $method = $or ? 'orWhereDoesntHave' : 'whereDoesntHave';
            $query->$method('values', $this->valueScope($field, $lang));
            return;
        }

        if ($hasEmpty) {
            $wrapper = $or ? 'orWhere' : 'where';
            $query->$wrapper(function ($q) use ($field, $lang, $nonEmpty, $subfield) {
                $q->whereHas('values', $this->valueScope($field, $lang, fn($q) =>
                    $field->typeClass()::filterIn($q, $nonEmpty, $subfield)
                ))->orWhereDoesntHave('values', $this->valueScope($field, $lang));
            });
            return;
        }

        $method = $or ? 'orWhereHas' : 'whereHas';
        $query->$method('values', $this->valueScope($field, $lang, fn($q) =>
            $field->typeClass()::filterIn($q, $values, $subfield)
        ));
    }

    function scopeWhereField($query, string $field_name, $op, $value = null)
    {
        if (func_num_args() < 4) { $value = $op; $op = '='; }
        $this->applyWhereField($query, $field_name, $op, $value, or: false, not: false);
    }

    function scopeWhereFieldNot($query, string $field_name, $op, $value = null)
    {
        if (func_num_args() < 4) { $value = $op; $op = '='; }
        $this->applyWhereField($query, $field_name, $op, $value, or: false, not: true);
    }

    function scopeOrWhereField($query, string $field_name, $op, $value = null)
    {
        if (func_num_args() < 4) { $value = $op; $op = '='; }
        $this->applyWhereField($query, $field_name, $op, $value, or: true, not: false);
    }

    function scopeOrWhereFieldNot($query, string $field_name, $op, $value = null)
    {
        if (func_num_args() < 4) { $value = $op; $op = '='; }
        $this->applyWhereField($query, $field_name, $op, $value, or: true, not: true);
    }

    function scopeWhereFieldIn($query, string $field_name, $values)
    {
        $this->applyWhereFieldIn($query, $field_name, $values, or: false, not: false);
    }

    function scopeWhereFieldNotIn($query, string $field_name, array $values)
    {
        $this->applyWhereFieldIn($query, $field_name, $values, or: false, not: true);
    }

    function scopeOrWhereFieldIn($query, string $field_name, array $values)
    {
        $this->applyWhereFieldIn($query, $field_name, $values, or: true, not: false);
    }

    function scopeOrWhereFieldNotIn($query, string $field_name, array $values)
    {
        $this->applyWhereFieldIn($query, $field_name, $values, or: true, not: true);
    }
}
