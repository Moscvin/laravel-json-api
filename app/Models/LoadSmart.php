<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoadSmart extends Model
{
    use HasFactory;

    protected $table = 'load_smart';

    protected $fillable = [
        'id_load_smart',
        'measure_name',
        'measure_value',
        'short_address_1',
        'hour_address_1',
        'short_address_2',
        'hour_address_2',
        'type',
        'bid_amount',
    ];

    protected $casts = [
        'hour_address_1' => 'datetime',
        'hour_address_2' => 'datetime',
        'measure_value' => 'decimal:2',
        'bid_amount' => 'decimal:2',
    ];

    public function changes()
    {
        return $this->hasMany(LoadSmartChanging::class, 'id_load', 'id_load_smart');
    }
}
