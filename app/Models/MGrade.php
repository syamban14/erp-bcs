<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MGrade extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';
    protected $table = 'm_grade';
    public $timestamps = false; 

    protected $primaryKey = 'grade_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
}
