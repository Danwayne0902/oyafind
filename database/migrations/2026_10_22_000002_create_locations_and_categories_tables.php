<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('location_type')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('catalogue_origin')->nullable();
            $table->string('source_name', 120)->nullable();
            $table->string('source_reference', 500)->nullable();
            $table->string('source_version', 120)->nullable();
            $table->date('source_retrieved_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'reviewed_at']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('icon_key', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('locations');
    }
};
