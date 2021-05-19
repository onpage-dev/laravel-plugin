<?php
namespace OnPage\Models; 
class Offerte2 extends \OnPage\Resource {
  public static function boot() {
      parent::boot();
      self::addGlobalScope('opres', function($q) {
        $q->whereRes(5119);
      });
      self::addGlobalScope('oplang', function($q) {
        $q->localized();
      });
      self::addGlobalScope('opmeta', function($q) {
        $q->loaded();
      });
    }
  public static function getResource() {
      return op_schema()->name_to_res['offerte2'];
    }
  function contact() {
    return $this->belongsToMany(contatti::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_contact')
    ->orderBy('meta_id');
  }
}
