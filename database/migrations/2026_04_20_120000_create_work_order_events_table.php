<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->uuid('correlation_id')->index();
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedBigInteger('order_item_no')->nullable()->index();
            $table->string('order_no', 50)->nullable()->index();
            $table->unsignedBigInteger('work_order_no')->nullable()->index();
            $table->unsignedBigInteger('pool_no')->nullable();
            $table->unsignedBigInteger('personnel_task_no')->nullable();
            $table->unsignedBigInteger('special_production_no')->nullable();
            $table->string('event_type', 100)->index();
            $table->string('event_group', 50)->index();
            $table->string('source_screen', 100)->nullable();
            $table->string('source_action', 100)->nullable();
            $table->string('source_route', 200)->nullable();
            $table->string('actor_type', 30)->default('system');
            $table->string('actor_id', 50)->nullable()->index();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_department', 150)->nullable();
            $table->string('status_before', 50)->nullable()->index();
            $table->string('status_after', 50)->nullable()->index();
            $table->string('title_human', 255);
            $table->text('summary_human');
            $table->text('next_step_human')->nullable();
            $table->json('payload_before')->nullable();
            $table->json('payload_after')->nullable();
            $table->json('context')->nullable();
            $table->dateTime('happened_at')->index();
            $table->timestamps();

            $table->index(['order_item_no', 'happened_at'], 'wo_events_order_item_happened_idx');
            $table->index(['work_order_no', 'happened_at'], 'wo_events_work_order_happened_idx');
            $table->index(['actor_id', 'happened_at'], 'wo_events_actor_happened_idx');
            $table->index(['aggregate_type', 'aggregate_id'], 'wo_events_aggregate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_events');
    }
};
