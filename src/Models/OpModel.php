<?php
namespace OnPage\Models;

class OpModel extends \Illuminate\Database\Eloquent\Model {
    public $guarded = [];
    public $timestamps = false;

    static function customUpsert($data, $primary_key) {
        foreach ($data as $row) {
            self::withoutGlobalScopes()->updateOrCreate([
                'id' => $row[$primary_key],
            ], $row);
        }
    }
}
