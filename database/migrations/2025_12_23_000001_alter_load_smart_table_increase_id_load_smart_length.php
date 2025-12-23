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
        Schema::table('load_smart', function (Blueprint $table) {
            $table->string('id_load_smart', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('load_smart', function (Blueprint $table) {
            $table->string('id_load_smart', 50)->change(); // assuming original was 50
        });
    }
};
