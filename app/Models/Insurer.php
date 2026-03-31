<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insurer extends Model
{
    use HasFactory;

    protected $table = 'insurers';

    protected $fillable = [
        'name',
        'code',
        'min_batch_size',
        'max_batch_size',
        'daily_capacity',
        'preferred_date_type',
        'specialty_efficiencies',
    ];

    protected $casts = [
        'specialty_efficiencies' => 'array',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function routeNotificationForMail(): string
    {
        return $this->email ?? 'admin@insurer.local';
    }
} 