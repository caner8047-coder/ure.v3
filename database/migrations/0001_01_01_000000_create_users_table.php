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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_no')->unique()->nullable(); // PersonelNo
            $table->string('name'); // Adi
            $table->string('surname')->nullable(); // Soyadi
            $table->text('address')->nullable(); // Adresi
            $table->string('phone')->nullable(); // Tel
            $table->string('email')->unique(); // Mail
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete(); // Maps to BolumAdiNo
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); // Sifre (SHA256 will be converted)
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
