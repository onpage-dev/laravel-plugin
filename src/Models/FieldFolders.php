<?php

namespace OnPage\Models;

use Illuminate\Database\Eloquent\Builder;

class FieldFolders extends OpModel
{
    protected $table = 'op_field_folders';

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

    public function fields()
    {
        return $this->belongsToMany(Field::class, FieldFolderField::class, 'folder_id');
    }

    public function form_fields()
    {
        return $this->fields()
            ->wherePivot('type', 'form_fields');
    }
    public function arrow_fields()
    {
        return $this->fields()
            ->wherePivot('type', 'arrow_fields');
    }
}
