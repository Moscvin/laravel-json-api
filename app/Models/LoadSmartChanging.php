<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoadSmartChanging extends Model
{
    use HasFactory;

    protected $table = 'load_smart_changing';

    protected $fillable = [
        'id_load',
        'email',
        'price_old',
        'price_new',
    ];

    protected $casts = [
        'price_old' => 'decimal:2',
        'price_new' => 'decimal:2',
    ];

    public function loadSmart()
    {
        return $this->belongsTo(LoadSmart::class, 'id_load', 'id_load_smart');
    }
}
