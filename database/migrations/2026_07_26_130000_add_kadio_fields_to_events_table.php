<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('invitation_mode')->default('sections')->after('invitation_content');
            $table->json('couple_info')->nullable()->after('invitation_mode');
            $table->json('invitation_settings')->nullable()->after('couple_info');
            $table->json('hosts')->nullable()->after('invitation_settings');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'invitation_mode',
                'couple_info',
                'invitation_settings',
                'hosts',
            ]);
        });
    }
};
