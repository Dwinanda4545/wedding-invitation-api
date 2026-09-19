<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('universal_invitation_token', 64)->nullable()->unique();
            $table->boolean('universal_invitation_enabled')->default(false);
            $table->string('universal_greeting', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['universal_invitation_token']);
            $table->dropColumn([
                'universal_invitation_token',
                'universal_invitation_enabled',
                'universal_greeting',
            ]);
        });
    }
};
