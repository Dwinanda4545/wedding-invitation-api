<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('envelope_transactions')
            && Schema::hasColumn('envelope_transactions', 'duitku_reference')
            && ! Schema::hasColumn('envelope_transactions', 'payment_reference')
        ) {
            Schema::table('envelope_transactions', function (Blueprint $table) {
                $table->renameColumn('duitku_reference', 'payment_reference');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('envelope_transactions')
            && Schema::hasColumn('envelope_transactions', 'payment_reference')
            && ! Schema::hasColumn('envelope_transactions', 'duitku_reference')
        ) {
            Schema::table('envelope_transactions', function (Blueprint $table) {
                $table->renameColumn('payment_reference', 'duitku_reference');
            });
        }
    }
};
