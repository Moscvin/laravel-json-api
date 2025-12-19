<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add new columns
            $table->string('username')->unique()->after('name')->nullable();
            $table->string('phone')->nullable()->after('email');
            $table->enum('type', ['user', 'admin', 'moderator'])->default('user')->after('phone');
            $table->boolean('is_blocked')->default(false)->after('type');
            $table->timestamp('last_active_at')->nullable()->after('is_blocked');

            // Add index for frequently queried columns
            $table->index('type');
            $table->index('is_blocked');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['is_blocked']);
            $table->dropIndex(['created_at']);
            $table->dropColumn([
                'username',
                'phone',
                'type',
                'is_blocked',
                'last_active_at',
            ]);
        });
    }
};
