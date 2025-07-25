<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'address',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'start_time',
        'end_time',
        'cost',
        'spots',
        'unlimited_spots',
        'image',
        'imported_from',
        'external_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cost' => 'decimal:2',
        'unlimited_spots' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>=', now())->orderBy('start_time');
    }

    public function scopePast($query)
    {
        return $query->where('end_time', '<', now())->orderBy('start_time', 'desc');
    }
}
