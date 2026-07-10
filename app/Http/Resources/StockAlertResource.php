<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? (object) $this->resource : $this->resource;

        return [
            'id' => $data->id ?? $data->No ?? 0,
            'component_id' => $data->component_id ?? $data->AraUrunNo ?? null,
            'component_name' => $data->component_name ?? $data->AraUrunAdi ?? null,
            'current_quantity' => isset($data->current_quantity) ? intval($data->current_quantity) : (isset($data->Adet) ? intval($data->Adet) : 0),
            'min_threshold' => isset($data->min_threshold) ? intval($data->min_threshold) : (isset($data->MinAdet) ? intval($data->MinAdet) : 0),
            'is_critical' => (isset($data->current_quantity) && isset($data->min_threshold)) 
                ? (intval($data->current_quantity) < intval($data->min_threshold)) 
                : true,
        ];
    }
}
