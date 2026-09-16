<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('whatsapp_device_id')
                ->nullable()
                ->constrained('whatsapp_devices')
                ->nullOnDelete();
        });

        Schema::table('invitation_sends', function (Blueprint $table) {
            $table->foreignId('whatsapp_device_id')
                ->nullable()
                ->constrained('whatsapp_devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitation_sends', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_device_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_device_id');
        });
    }
};
