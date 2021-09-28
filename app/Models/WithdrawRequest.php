<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    use HasFactory;

    protected $casts = [
        'amount'=>'float'
    ];

    public function vendor(){
        return $this->belongsTo(Vendor::class);
    }
}
