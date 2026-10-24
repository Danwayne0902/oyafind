<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->nullable()->unique();
            $table->string('phone', 25)->nullable()->unique();
            $table->string('password')->nullable();
            // Role defaults to the least-privileged account; status has no approved default.
            $table->string('role')->default('user');
            $table->string('status')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->json('interests')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
