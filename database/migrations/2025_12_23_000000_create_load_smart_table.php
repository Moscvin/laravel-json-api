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
        Schema::create('load_smart', function (Blueprint $table) {
            $table->id();
            $table->string('id_load_smart')->unique();
            $table->string('measure_name')->nullable();
            $table->decimal('measure_value', 10, 2)->nullable();
            $table->string('short_address_1')->nullable();
            $table->string('hour_address_1')->nullable();
            $table->string('short_address_2')->nullable();
            $table->string('hour_address_2')->nullable();
            $table->string('type')->nullable();
            $table->decimal('bid_amount', 10, 2)->nullable();
            $table->decimal('match_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('load_smart');
    }
};
