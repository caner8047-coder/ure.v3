<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        if (!Schema::hasTable('app_settings')) {
            return $default;
        }

        $row = DB::table('app_settings')->where('key', $key)->first();
        if (!$row) {
            return $default;
        }

        return $this->castValue($row->value, (string) ($row->type ?? 'string'), $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        if (!Schema::hasTable('app_settings')) {
            throw new \RuntimeException('Ayarlar tablosu bulunamadı. Migration çalıştırılmalı.');
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function castValue(mixed $value, string $type, mixed $default): mixed
    {
        return match ($type) {
            'boolean' => $this->castBoolean($value, (bool) $default),
            'integer' => is_numeric($value) ? (int) $value : $default,
            'json' => $this->castJson($value, $default),
            default => $value ?? $default,
        };
    }

    private function castBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    private function castJson(mixed $value, mixed $default): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    private function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
