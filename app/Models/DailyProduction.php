<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyProduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bread_id',
        'produced_by',
        'production_date',
        'quantity_produced',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'quantity_produced' => 'integer',
        ];
    }

    public function bread(): BelongsTo
    {
        return $this->belongsTo(Bread::class);
    }

    public function producedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }
}