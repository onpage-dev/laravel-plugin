<?php
namespace OnPage\Models; 
class Prodotti extends \OnPage\Resource {
  public static function boot() {
      parent::boot();
      self::addGlobalScope('opres', function($q) {
        $q->whereRes(550);
      });
      self::addGlobalScope('oplang', function($q) {
        $q->localized();
      });
      self::addGlobalScope('opmeta', function($q) {
        $q->loaded();
      });
    }
  public static function getResource() {
      return op_schema()->name_to_res['prodotti'];
    }
  function argomenti() {
    return $this->belongsToMany(argomenti::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_argomenti')
    ->orderBy('meta_id');
  }
  function articoli() {
    return $this->belongsToMany(articoli::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_articoli')
    ->orderBy('meta_id');
  }
  function offerte() {
    return $this->belongsToMany(offerte::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_offerte')
    ->orderBy('meta_id');
  }
  function genere() {
    return $this->belongsToMany(genere::class, \OnPage\Meta::class, 'id', 'meta_value', null)
    ->wherePivot('meta_key', 'oprel_genere')
    ->orderBy('meta_id');
  }
}
