<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MDept extends Model
{
    protected $connection = 'pgsql_master';
    protected $table = 'm_dept';
    public $timestamps = false;
    
    protected $fillable = ['dept_name'];
}
