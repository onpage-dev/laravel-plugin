<?php
namespace OnPage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Model extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    public $guarded = [];
    public $timestamps = false;
}
