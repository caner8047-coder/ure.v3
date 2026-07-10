<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Standardize properties from database array/object rows or Eloquent models
        $data = is_array($this->resource) ? (object) $this->resource : $this->resource;

        $adet = isset($data->Adet) ? intval($data->Adet) : 0;
        $bekleyen = isset($data->BekleyenAdet) ? intval($data->BekleyenAdet) : 0;
        $onayVal = isset($data->Onay) ? trim((string) $data->Onay) : '0';
        $approved = $onayVal === '1' || strtolower($onayVal) === 'true';

        return [
            'id' => isset($data->No) ? intval($data->No) : (isset($data->id) ? intval($data->id) : 0),
            'start_date' => $data->GorevBaslamaTarihi ?? null,
            'end_date' => $data->GorevBitisTarihi ?? null,
            'product_name' => $data->UrunID ?? null,
            'component_name' => $data->AraUrunAdi ?? null,
            'quantity' => $adet,
            'pending_quantity' => $bekleyen,
            'is_approved' => $approved,
            'is_completed' => $bekleyen <= 0 && $adet > 0,
            'status' => ($bekleyen <= 0 && $adet > 0) ? 'completed' : ($approved ? 'in_progress' : 'pending'),
        ];
    }
}
