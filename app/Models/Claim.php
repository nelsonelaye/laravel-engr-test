<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    use HasFactory;

    protected $table = 'claims';

    protected $fillable = [
        'user_id',
        'insurer_id',
        'batch_id',
        'provider_name',
        'encounter_date',
        'submission_date',
        'specialty',
        'priority_level',
        'total_amount',
        'processing_cost',
        'status',
    ];

    protected $casts = [
        'encounter_date' => 'date',
        'submission_date' => 'date',
        'total_amount' => 'decimal:2',
        'processing_cost' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClaimItem::class);
    }

    public function getCalculatedTotalAttribute(): float
    {
        return $this->items->sum(fn($item) => $item->unit_price * $item->quantity);
    }
} 