<?php
namespace OnPage\Models; 
class Argomenti extends \OnPage\Resource {
  public static function boot() {
      parent::boot();
      self::addGlobalScope('opres', function($q) {
        $q->whereRes(549);
      });
      self::addGlobalScope('oplang', function($q) {
        $q->localized();
      });
      self::addGlobalScope('opmeta', function($q) {
        $q->loaded();
      });
    }
  public static function getResource() {
      return op_schema()->name_to_res['argomenti'];
    }
  function capitoli() {
    return $this->belongsToMany(capitoli::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_capitoli')
    ->orderBy('meta_id');
  }
  function prodotti() {
    return $this->belongsToMany(prodotti::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_prodotti')
    ->orderBy('meta_id');
  }
}
