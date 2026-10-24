<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'opportunity_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->string('status')->nullable();
            $table->string('status_source')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'opportunity_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('opportunity_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->text('details')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->dateTime('resolved_at')->nullable();
            $table->index(['status', 'created_at']);
            $table->index(['opportunity_id', 'created_at']);
        });

        Schema::create('opportunity_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('result');
            $table->string('check_type', 80);
            $table->string('evidence_url', 2048)->nullable();
            $table->text('evidence_summary')->nullable();
            $table->text('evidence_rationale')->nullable();
            $table->text('restricted_notes')->nullable();
            $table->dateTime('checked_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['opportunity_id', 'checked_at']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->json('data')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('opportunity_verifications');
        Schema::dropIfExists('opportunity_reports');
        Schema::dropIfExists('user_applications');
        Schema::dropIfExists('saved_opportunities');
    }
};
