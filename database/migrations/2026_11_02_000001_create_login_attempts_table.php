<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent sign-in lockout state, kept separately from the short-lived
 * rate limiter. The limiter slows a burst; this table remembers repeated
 * failures long enough that an attacker cannot simply wait out a one-minute
 * window and keep guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier_hash', 64)->unique();
            $table->string('identifier', 190);
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
