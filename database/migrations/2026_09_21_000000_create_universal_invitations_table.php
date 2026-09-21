<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universal_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('greeting', 255)->nullable();
            $table->string('token', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['universal_invitation_token']);
            $table->dropColumn([
                'universal_invitation_token',
                'universal_invitation_enabled',
                'universal_greeting',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('universal_invitation_token', 64)->nullable()->unique();
            $table->boolean('universal_invitation_enabled')->default(false);
            $table->string('universal_greeting', 255)->nullable();
        });

        Schema::dropIfExists('universal_invitations');
    }
};
