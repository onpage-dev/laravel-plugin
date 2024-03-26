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
}
