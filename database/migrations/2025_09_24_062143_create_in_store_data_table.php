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
        Schema::create('in_store_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('aisle_number'); // e.g., "A1", "B2", "12"
            $table->string('aisle_name')->nullable(); // e.g., "Dairy Section"
            $table->decimal('x_coordinate', 10, 2)->nullable(); // Position on DXF
            $table->decimal('y_coordinate', 10, 2)->nullable(); // Position on DXF
            $table->integer('floor_level')->default(1); // Ground floor = 1
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Ensure unique aisle numbers per shop
            $table->unique(['shop_id', 'aisle_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_store_data');
    }
};
