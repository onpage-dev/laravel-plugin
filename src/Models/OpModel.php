<?php
namespace OnPage\Models;


class OpModel extends \Illuminate\Database\Eloquent\Model
{
    public $guarded = [];
    public $timestamps = false;

    static function customUpsert(array $data, $primary_key) {
        if (method_exists(self::class, 'upsert')) {
            self::upsert($data, $primary_key);
        } else {
            foreach ($data as $row) {
                self::updateOrCreate([
                    'id' => $row[$primary_key],
                ], $row);
            }
        }
    }
}