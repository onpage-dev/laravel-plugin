<?php
namespace OnPage\Models; 
class Articoli extends \OnPage\Resource {
  public static function boot() {
      parent::boot();
      self::addGlobalScope('opres', function($q) {
        $q->whereRes(551);
      });
      self::addGlobalScope('oplang', function($q) {
        $q->localized();
      });
      self::addGlobalScope('opmeta', function($q) {
        $q->loaded();
      });
    }
  public static function getResource() {
      return op_schema()->name_to_res['articoli'];
    }
  function prodotti() {
    return $this->belongsToMany(prodotti::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_prodotti')
    ->orderBy('meta_id');
  }
  function prezzi() {
    return $this->belongsToMany(prezzi::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_prezzi')
    ->orderBy('meta_id');
  }
}
