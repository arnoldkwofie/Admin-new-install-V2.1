<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'start_date','end_date','start_time','end_time','min_purchase','max_discount','discount','discount_type','restaurant_id',
    ];
    protected $casts = [
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'discount' => 'float',
    ];
    protected $dates = ['created_at', 'updated_at', 'start_date', 'end_date', 'start_time', 'end_time'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
