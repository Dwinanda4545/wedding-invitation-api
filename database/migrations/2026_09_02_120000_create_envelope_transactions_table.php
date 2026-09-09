<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envelope_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('sender_name');
            $table->string('sender_email')->nullable();
            $table->string('sender_phone')->nullable();
            $table->unsignedBigInteger('amount');
            $table->text('message')->nullable();
            $table->string('order_id')->unique();
            $table->string('payment_method')->nullable();
            $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->text('payment_url')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envelope_transactions');
    }
};
