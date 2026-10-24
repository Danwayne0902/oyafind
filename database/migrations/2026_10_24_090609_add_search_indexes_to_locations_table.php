<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->index('name');
            $table->index('location_type');
            $table->index('parent_id');
            $table->index(['is_active', 'parent_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE locations ADD FULLTEXT INDEX locations_name_fulltext (name)');
            } catch (\Throwable $e) {
                // FULLTEXT may not be supported in some MySQL configurations; regular indexes remain.
            }
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropIndex(['name']);
            $table->dropIndex(['location_type']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_active', 'parent_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE locations DROP INDEX locations_name_fulltext');
            } catch (\Throwable $e) {
                // Ignore if the index does not exist.
            }
        }
    }
};
