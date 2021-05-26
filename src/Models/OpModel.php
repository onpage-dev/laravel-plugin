<?php
namespace OnPage\Models;


class OpModel extends \Illuminate\Database\Eloquent\Model
{
    public $guarded = [];
    public $timestamps = false;

    static function customUpsert(array $data, $primary_key) {
        if (method_exists($this, 'upsert')) {
            $this->upsert($data, 'upsert');
        } else {
            foreach ($data as $row) {
                $row = (array) $row;
                self::updateOrCreate([
                    'id' => $row[$primary_key],
                ], $data);
            }
        }
    }
}