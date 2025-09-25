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
        Schema::create('shop_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('dxf_file_name');
            $table->string('dxf_file_path');
            $table->json('bounds')->nullable(); // Store DXF bounds as JSON
            $table->json('line_segments')->nullable(); // Store line segments as JSON
            $table->boolean('use_on_demand_rendering')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_layouts');
    }
};
