<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MDivision extends Model
{
    protected $connection = 'pgsql_master';
    protected $table = 'm_division';
    public $timestamps = false;
    
    protected $fillable = ['div_name'];
}
