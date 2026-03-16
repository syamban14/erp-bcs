<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MTitle extends Model
{
    protected $connection = 'pgsql_master';
    protected $table = 'm_title';
    public $timestamps = false;
    
    protected $fillable = ['title_name'];
}
