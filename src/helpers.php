<?php

namespace OnPage;

use Illuminate\Support\Collection;

function curl_get($url, callable $on_error): object
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    if (curl_errno($ch) || !$result) {
        $on_error($ch);
    }
    curl_close($ch);

    return \json_decode($result);
}

function generate_model_file(object $res)
{
    $namespace = config('onpage.models_namespace');
    if (!$namespace) {
        throw new \Exception("Invalid model namespace");
    }
    $camel_name = to_camel_case($res->name);
    $code = "<?php\nnamespace $namespace; \n";
    $code .= "// DO NOT EDIT THIS FILE!\n";
    $code .= "// This file is automatically generated and will be overwritten during the next import\n";
    $code .= "class $camel_name extends \OnPage\Models\Thing {\n";
    $code .= "  protected \$table = 'op_things'; \n";
    $code .= "  static public \$RESOURCE_ID = $res->id; \n";
    $code .= "
  function getResource() : \OnPage\Models\Resource {
    return \OnPage\Cache::idToResource($res->id);
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
            $order_clause = op_major_laravel_version() >= 8 ? "->orderByPivot('id')" : "->orderBy('op_relations.id')";
            $code .= "  function $f->name() {\n";
            $code .= "    return \$this->belongsToMany($rel_class::class, \OnPage\Models\Relation::class,'thing_from_id','thing_to_id')\n";
            $code .= "      $order_clause\n";
            $code .= "      ->wherePivot('field_id', $f->id);\n";
            $code .= "  }\n";
        }
    }
    $code .= "}\n";
    $dir = config('onpage.models_dir');
    if (!$dir) {
        throw new \Exception("Invalid model directory");
    }
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    file_put_contents("$dir/$camel_name.php", $code);
}

function to_camel_case($str)
{
    $str = explode('_', $str);
    $ret = '';
    foreach ($str as $s) {
        $ret .= strtoupper(@$s[0]) . substr($s, 1);
    }
    return $ret;
}

function op_url(string $token, string $name = null): string
{
    $domain = config('onpage.company');
    $url = "https://storage.onpage.it/$token";
    if ($name) {
        $url .= '?' . http_build_query([
            'name' => $name,
        ]);
    }
    return $url;
}

function op_lang(string $set = null)
{
    static $current;
    if ($set) {
        $current = $set;
    } elseif ($current) {
        return $current;
    }
    return app()->getLocale();
}

function get_backtrace(bool $exclude_vendor = false): array
{
    $trace = debug_backtrace();
    $ret = [];
    foreach ($trace as $i => $call) {
        if ($exclude_vendor && preg_match('/vendor/', @$call['file'])) {
            continue;
        }
        $ret[] = @"{$call['file']}:{$call['line']}\n";
    }
    return $ret;
}
function log_backtrace(bool $exclude_vendor = true)
{
    $ram = str_pad(number_format(memory_get_usage(true) / 1024 / 1024, 1), 6, " ", STR_PAD_LEFT);
    $file = trim(get_backtrace($exclude_vendor)[2]);
    $file = str_replace(base_path(), '', $file);
    echo ("Backtrace $ram MB  $file\n");
}

function resource($id): ?Models\Resource
{
    if (is_numeric($id)) {
        return \OnPage\Cache::idToResource($id);
    } else {
        return \OnPage\Cache::nameToResource($id);
    }
}


function resources(): Collection
{
    return Cache::resources();
}

function op_major_laravel_version(): int
{
    return explode('.', app()->version())[0];
}
