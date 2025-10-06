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
        Schema::table('waypoints', function (Blueprint $table) {
            $table->boolean('is_start_point')->default(false)->after('description');
            $table->boolean('is_end_point')->default(false)->after('is_start_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waypoints', function (Blueprint $table) {
            $table->dropColumn(['is_start_point', 'is_end_point']);
        });
    }
};
