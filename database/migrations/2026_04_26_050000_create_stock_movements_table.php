<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('movement_uuid')->unique();

            $table->unsignedBigInteger('stock_row_no')->nullable()->index();
            $table->unsignedBigInteger('component_no')->nullable()->index();
            $table->unsignedBigInteger('department_no')->nullable()->index();
            $table->unsignedBigInteger('product_no')->nullable()->index();

            $table->string('movement_type', 60)->index();
            $table->string('direction', 20)->index();
            $table->string('title_human', 255);

            $table->integer('quantity_before')->nullable();
            $table->integer('quantity_delta')->default(0);
            $table->integer('quantity_after')->nullable();
            $table->integer('buffer_before')->nullable();
            $table->integer('buffer_delta')->default(0);
            $table->integer('buffer_after')->nullable();

            $table->string('source_type', 60)->nullable()->index();
            $table->string('source_id', 150)->nullable()->index();
            $table->unsignedBigInteger('order_item_no')->nullable()->index();
            $table->string('order_no', 50)->nullable()->index();
            $table->unsignedBigInteger('work_order_no')->nullable()->index();
            $table->unsignedBigInteger('pool_no')->nullable()->index();
            $table->unsignedBigInteger('personnel_task_no')->nullable()->index();

            $table->string('source_screen', 100)->nullable();
            $table->string('source_action', 100)->nullable();
            $table->string('source_route', 200)->nullable();
            $table->string('actor_type', 30)->default('system');
            $table->string('actor_id', 50)->nullable()->index();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_department', 150)->nullable();

            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('happened_at')->index();
            $table->timestamps();

            $table->index(['component_no', 'department_no', 'happened_at'], 'stock_mov_component_dept_time_idx');
            $table->index(['stock_row_no', 'happened_at'], 'stock_mov_row_time_idx');
            $table->index(['source_type', 'source_id'], 'stock_mov_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
