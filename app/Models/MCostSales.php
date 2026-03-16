<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MCostSales extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';
    protected $table = 'm_cost_sales';
    public $timestamps = false; 

    protected $primaryKey = 'cost_sales_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
}
