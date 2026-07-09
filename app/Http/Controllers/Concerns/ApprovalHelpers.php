<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

trait ApprovalHelpers
{
    private function castColumnSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "LOWER(TRIM(CAST({$column} AS TEXT)))"
            : "LOWER(TRIM(CAST({$column} AS CHAR)))";
    }

    private function openApprovalSql(string $column = 'Onay'): string
    {
        $normalized = $this->castColumnSql($column);

        return "({$column} IS NULL OR TRIM(CAST({$column} AS TEXT)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    private function productionReadyApprovalSql(string $column = 'Onay'): string
    {
        $normalized = $this->castColumnSql($column);

        return "({$normalized} IN ('hazir', 'ready'))";
    }

    private function notProductionReadyApprovalSql(string $column = 'Onay'): string
    {
        $normalized = $this->castColumnSql($column);

        return "({$normalized} NOT IN ('hazir', 'ready'))";
    }

    private function inProductionApprovalSql(string $column = 'Onay'): string
    {
        $normalized = $this->castColumnSql($column);

        return "({$normalized} IN ('1', 'true', 'evet', 'yes'))";
    }

    private function assignedWaitingApprovalSql(string $column = 'Onay'): string
    {
        $normalized = $this->castColumnSql($column);

        return "({$normalized} IN ('atandi', 'assigned', 'bekliyor', 'waiting'))";
    }

    private function activeProductionApprovalSql(string $column = 'Onay'): string
    {
        $normalized = $this->castColumnSql($column);

        return "({$normalized} IN ('uretimde', 'in_production', 'basladi', 'started'))";
    }

    private function isProductionReadyApproval(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['hazir', 'ready'], true);
    }

    private function isApprovedValue(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'evet', 'yes'], true);
    }

    private function isActiveProductionApproval(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['uretimde', 'in_production', 'basladi', 'started'], true);
    }
}
