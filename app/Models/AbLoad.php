<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbLoad extends Model
{
    use HasFactory;

    protected $table = 'ab_loads';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'status',
        'time_status',
        'message_id',
        'fromName',
        'from',
        'subject',
        'title',
        'received_at',
        'filePathHTML',
        'filePathTXT',
        'filePathPDF',
        'rate',
        'manual_rate',
        'commodity',
        'comment',
        'gate_check_in',
        'load_id',
        'shipper',
        'pull_date',
        'pull_time',
        'pull_datetime',
        'manual_pull_date',
        'manual_pull_time',
        'manual_pull_datetime',
        'consignee',
        'delivery_date',
        'delivery_time',
        'delivery_datetime',
        'manual_delivery_date',
        'manual_delivery_time',
        'manual_delivery_datetime',
        'consignee_2',
        'delivery_date_2',
        'delivery_time_2',
        'delivery_datetime_2',
        'manual_delivery_date_2',
        'manual_delivery_time_2',
        'manual_delivery_datetime_2',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'time_status' => 'datetime',
        'received_at' => 'datetime',
        'pull_datetime' => 'datetime',
        'manual_pull_datetime' => 'datetime',
        'delivery_datetime' => 'datetime',
        'manual_delivery_datetime' => 'datetime',
        'delivery_datetime_2' => 'datetime',
        'manual_delivery_datetime_2' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'rate' => 'decimal:2',
        'manual_rate' => 'decimal:2',
    ];

    /**
     * Relationship to User (uid foreign key)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'id');
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'Status Not Set' => 'Not Set',
            'In Sales Process' => 'In Sales',
            'Sale Confirmed' => 'Confirmed',
            'Load Picked Up' => 'Picked Up',
            'In Transit' => 'In Transit',
            'Delivered' => 'Delivered',
            'Completed' => 'Completed',
        ];

        return $statuses[$this->status] ?? $this->status;
    }
}
