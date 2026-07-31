<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bread' => [
                'id' => $this->bread->id,
                'name' => $this->bread->name,
                'sku' => $this->bread->sku,
            ],
            'inventory_date' => $this->inventory_date->toDateString(),
            'opening_stock' => $this->opening_stock,
            'closing_stock' => $this->closing_stock,
            'sold_quantity' => $this->sold_quantity,
            'revenue' => (float) $this->revenue,
            'recorded_by' => [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}