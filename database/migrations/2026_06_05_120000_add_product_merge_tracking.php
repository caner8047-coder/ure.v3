<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMergeColumns('tbUrunler');
        $this->addMergeColumns('tbAraUrun');

        if (!Schema::hasTable('product_merge_logs')) {
            Schema::create('product_merge_logs', function (Blueprint $table) {
                $table->id();
                $table->string('merge_type', 30)->index();
                $table->unsignedBigInteger('source_no')->index();
                $table->string('source_name', 500)->nullable();
                $table->unsignedBigInteger('target_no')->index();
                $table->string('target_name', 500)->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('actor_name', 200)->nullable();
                $table->json('preview')->nullable();
                $table->json('applied_changes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_merge_logs');

        $this->dropMergeColumns('tbAraUrun');
        $this->dropMergeColumns('tbUrunler');
    }

    private function addMergeColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (!Schema::hasColumn($table, 'MergedIntoNo')) {
                $blueprint->integer('MergedIntoNo')->nullable()->index();
            }

            if (!Schema::hasColumn($table, 'MergedAt')) {
                $blueprint->dateTime('MergedAt')->nullable();
            }

            if (!Schema::hasColumn($table, 'MergedBy')) {
                $blueprint->integer('MergedBy')->nullable()->index();
            }
        });
    }

    private function dropMergeColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            foreach (['MergedBy', 'MergedAt', 'MergedIntoNo'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
