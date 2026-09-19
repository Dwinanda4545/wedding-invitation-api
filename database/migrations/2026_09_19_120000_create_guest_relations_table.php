<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'label']);
            $table->index(['event_id', 'sort_order']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->foreignId('guest_relation_id')
                ->nullable()
                ->after('guest_type')
                ->constrained('guest_relations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_relation_id');
        });

        Schema::dropIfExists('guest_relations');
    }
};
