<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 64);
            $table->unsignedBigInteger('task_no')->nullable();
            $table->unsignedBigInteger('order_no')->nullable();
            $table->unsignedBigInteger('order_item_no')->nullable();
            $table->text('message_body');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_type', 'task_no']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_notification_logs');
    }
};
