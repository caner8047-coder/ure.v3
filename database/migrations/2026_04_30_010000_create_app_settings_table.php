<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type', 40)->default('string');
                $table->timestamps();
            });
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'work_order_bom_preview_enabled'],
            [
                'value' => '1',
                'type' => 'boolean',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
