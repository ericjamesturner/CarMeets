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
        // Drop and recreate columns to ensure they're the right type
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['description', 'image']);
        });
        
        Schema::table('events', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->text('image')->nullable()->after('unlimited_spots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['description', 'image']);
        });
        
        Schema::table('events', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->string('image')->nullable()->after('unlimited_spots');
        });
    }
};
