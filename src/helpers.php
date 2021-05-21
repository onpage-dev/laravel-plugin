
function op_gen_model(object $res) {
    $camel_name = op_snake_to_camel($res->name);
    //$extends = $res->is_product ? 'Post' : 'Term';
    //$extends_lc = strtolower($extends);
  
    $code = "<?php\nnamespace OnPage\\Op; \n";
    $code.= "class $camel_name extends \OnPage\Models\Thing {\n";
    $code.= "  protected \$table = 'things'; \n";
    $code.= "  public static function boot() {
      parent::boot();
      self::addGlobalScope('resource', function(\$q) {
        \$q->whereResource_id($res->id);
      });
    }\n";
/*       self::addGlobalScope('oplang', function(\$q) {
        \$q->localized();
      });
      self::addGlobalScope('opmeta', function(\$q) {
        \$q->loaded();
      }); */
      
    foreach ($res->fields as $f) {
      if ($f->type == 'relation') {
        //$rel_method = $f->rel_res->is_product ? 'posts' : 'terms';
        $rel_class=Models\Resource::find($f->rel_res_id)->name;
        $rel_class = op_snake_to_camel($rel_class);
        //$rel_class_primary = $f->rel_res->is_product ? 'ID' : 'term_id';
        $code.= "  function $f->name() {\n";
        $code.= "    return \$this->belongsToMany($rel_class::class, \OnPage\Models\Relation::class,'thing_from_id','thing_to_id');\n";
        $code.= "  }\n";
      }
    }
    $code.= "}\n";
    $file = __DIR__."/../src/Op/$camel_name.php";
    file_put_contents($file, $code);
    
  }
  
  function op_snake_to_camel($str) {
      $str = explode('_', $str);
      $ret = '';
      foreach ($str as $s) {
        $ret.= strtoupper(@$s[0]).substr($s, 1);
      }
      return $ret;
    }