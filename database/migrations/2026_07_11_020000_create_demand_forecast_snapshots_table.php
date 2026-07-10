<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demand_forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();     // tbUrunler.No referansı
            $table->unsignedBigInteger('component_id')->nullable();   // tbAraUrun.No referansı
            $table->date('period_date');
            $table->enum('period_type', ['weekly', 'monthly'])->default('monthly');
            $table->integer('actual_demand')->default(0);
            $table->decimal('forecasted_demand', 10, 2)->nullable();
            $table->decimal('confidence_lower', 10, 2)->nullable();
            $table->decimal('confidence_upper', 10, 2)->nullable();
            $table->decimal('mape', 8, 4)->nullable();      // Mean Absolute Percentage Error
            $table->enum('status', ['predicted', 'approved', 'rejected', 'overridden'])->default('predicted');
            $table->integer('override_value')->nullable();    // Planlayıcı override
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('ai_summary')->nullable();           // AIBrainService Türkçe özeti
            $table->json('model_metadata')->nullable();       // Prophet parametreleri
            $table->timestamps();

            $table->index(['product_id', 'period_date']);
            $table->index(['component_id', 'period_date']);
            $table->index(['period_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_forecast_snapshots');
    }
};
