<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreadOpeningBalance extends Model
{
    protected $fillable = [
        'bread_id',
        'quantity',
        'note',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function bread(): BelongsTo
    {
        return $this->belongsTo(Bread::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}