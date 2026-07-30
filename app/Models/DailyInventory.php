<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyInventory extends Model
{
    protected $fillable = [
        'bread_id',
        'recorded_by',
        'inventory_date',
        'opening_stock',
        'closing_stock',
        'sold_quantity',
        'revenue',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date',
            'opening_stock' => 'integer',
            'closing_stock' => 'integer',
            'sold_quantity' => 'integer',
            'revenue' => 'decimal:2',
        ];
    }

    public function bread(): BelongsTo
    {
        return $this->belongsTo(Bread::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}