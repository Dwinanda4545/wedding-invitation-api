<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envelope_transactions', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->unsignedBigInteger('guest_id')->nullable()->change();
            $table->foreign('guest_id')->references('id')->on('guests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('envelope_transactions', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->unsignedBigInteger('guest_id')->nullable(false)->change();
            $table->foreign('guest_id')->references('id')->on('guests')->cascadeOnDelete();
        });
    }
};
