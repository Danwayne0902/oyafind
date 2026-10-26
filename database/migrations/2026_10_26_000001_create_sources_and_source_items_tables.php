<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('homepage_url', 2048);
            $table->string('feed_url', 2048);
            $table->string('adapter', 30);
            $table->string('default_category_slug', 80)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('attribution', 200)->nullable();
            $table->string('terms_url', 2048)->nullable();
            $table->boolean('enabled')->default(true);
            $table->dateTime('last_fetched_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_item_count')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'last_fetched_at']);
        });

        Schema::create('source_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 200)->nullable();
            $table->char('url_hash', 64)->unique();
            $table->string('canonical_url', 2048);
            $table->string('title', 300);
            $table->string('organisation_name', 200)->nullable();
            $table->string('summary', 500)->nullable();
            $table->text('excerpt')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->string('location_text', 200)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('category_slug', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('status_reason', 200)->nullable();
            $table->char('dedupe_key', 64)->nullable();
            $table->dateTime('fetched_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['source_id', 'status']);
            $table->index('dedupe_key');
            $table->index('deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_items');
        Schema::dropIfExists('sources');
    }
};
