<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $table = 'batches';

    protected $fillable = [
        'insurer_id',
        'provider_name',
        'batch_date',
        'status',
        'total_claims',
        'total_cost',
    ];

    protected $casts = [
        'batch_date' => 'date',
        'total_cost' => 'decimal:2',
    ];

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }
}
