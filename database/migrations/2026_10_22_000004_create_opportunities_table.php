<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 230)->unique();
            $table->string('summary', 300);
            $table->longText('description');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('organisation_name', 200);
            $table->string('source_url', 2048)->nullable();
            $table->string('application_url', 2048)->nullable();
            // VARCHAR columns intentionally avoid treating proposed values as policy.
            $table->string('application_method')->nullable();
            $table->text('application_instructions')->nullable();
            $table->string('opportunity_type')->nullable();
            $table->string('work_mode')->nullable();
            $table->text('eligibility')->nullable();
            $table->json('requirements')->nullable();
            $table->decimal('amount_min', 12, 2)->nullable();
            $table->decimal('amount_max', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['location_id', 'status']);
            $table->index('deadline_at');
            $table->index('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
