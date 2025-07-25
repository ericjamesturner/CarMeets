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
        Schema::table('events', function (Blueprint $table) {
            $table->string('imported_from')->nullable()->after('image');
            $table->integer('external_id')->nullable()->after('imported_from');
            $table->index(['imported_from', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['imported_from', 'external_id']);
            $table->dropColumn(['imported_from', 'external_id']);
        });
    }
};
