<?php

namespace OnPage;

function generate_model_file(object $res) {
  $camel_name = to_camel_case($res->name);
  $code = "<?php\nnamespace Data; \n";
  $code .= "class $camel_name extends \OnPage\Models\Thing {\n";
  $code .= "  protected \$table = 'things'; \n";
  $code .= "  static public \$RESOURCE_ID = $res->id; \n";
  $code .= "
  function getResource() : \OnPage\Models\Resource {
    return \OnPage\Models\Resource::findFast($res->id);
  }
";


  $code .= "  public static function boot() {
  parent::boot();
  self::addGlobalScope('resource', function(\$q) {
      \$q->whereResource_id($res->id);
  });
  }\n";
  foreach ($res->fields as $f) {
    if ($f->type == 'relation') {
      $rel_class = Models\Resource::find($f->rel_res_id)->name;
      $rel_class = to_camel_case($rel_class);
      $code .= "  function $f->name() {\n";
      $code .= "    return \$this->belongsToMany($rel_class::class, \OnPage\Models\Relation::class,'thing_from_id','thing_to_id');\n";
      $code .= "  }\n";
    }
  }
  $code .= "}\n";
  $file = __DIR__ . "/../data/$camel_name.php";
  file_put_contents($file, $code);
}

function to_camel_case($str) {
  $str = explode('_', $str);
  $ret = '';
  foreach ($str as $s) {
    $ret .= strtoupper(@$s[0]) . substr($s, 1);
  }
  return $ret;
}



function op_url(string $token, string $name = null) : string {
  $domain = env('ONPAGE_COMPANY');
  $url = "https://{$domain}.onpage.it/api/storage/$token";
  if ($name) {
      $url .= '?'.http_build_query([
          'name' => $name,
      ]);
  }
  return $url;
}

function op_lang(string $set = null) {
  static $current;
  if ($set) {
      $current = $set;
  } elseif (!$current) {
      $current = app()->getLocale();
  }
  return $current;
}

