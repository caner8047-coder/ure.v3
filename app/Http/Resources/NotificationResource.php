<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'id' => $data->id ?? 0,
            'type' => $data->type ?? 'general',
            'title' => $data->title ?? 'Sistem Bildirimi',
            'message' => $data->message ?? ($data->data['message'] ?? $data->data ?? ''),
            'read_at' => $data->read_at ?? null,
            'created_at' => isset($data->created_at) ? (is_string($data->created_at) ? $data->created_at : $data->created_at->toIso8601String()) : null,
        ];
    }
}
