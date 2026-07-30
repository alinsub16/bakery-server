<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyProductionResource extends JsonResource
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
            'production_date' => $this->production_date->toDateString(),
            'quantity_produced' => $this->quantity_produced,
            'produced_by' => [
                'id' => $this->producedBy->id,
                'name' => $this->producedBy->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}