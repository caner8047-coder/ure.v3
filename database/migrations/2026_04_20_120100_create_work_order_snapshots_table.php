<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedBigInteger('order_item_no')->nullable()->index();
            $table->string('order_no', 50)->nullable()->index();
            $table->unsignedBigInteger('work_order_no')->nullable()->index();
            $table->string('current_status', 50)->nullable()->index();
            $table->string('current_stage', 50)->nullable()->index();
            $table->string('current_holder_type', 50)->nullable();
            $table->string('current_holder_id', 50)->nullable();
            $table->string('current_holder_name', 150)->nullable();
            $table->unsignedBigInteger('linked_special_production_no')->nullable();
            $table->string('next_expected_action', 255)->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->dateTime('last_changed_at')->nullable();
            $table->integer('alert_count')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['aggregate_type', 'aggregate_id'], 'wo_snapshots_aggregate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_snapshots');
    }
};
