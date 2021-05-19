<?php
namespace OnPage\Models; 
class Capitoli extends \OnPage\Resource {
  public static function boot() {
      parent::boot();
      self::addGlobalScope('opres', function($q) {
        $q->whereRes(548);
      });
      self::addGlobalScope('oplang', function($q) {
        $q->localized();
      });
      self::addGlobalScope('opmeta', function($q) {
        $q->loaded();
      });
    }
  public static function getResource() {
      return op_schema()->name_to_res['capitoli'];
    }
  function argomenti() {
    return $this->belongsToMany(argomenti::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_argomenti')
    ->orderBy('meta_id');
  }
}
