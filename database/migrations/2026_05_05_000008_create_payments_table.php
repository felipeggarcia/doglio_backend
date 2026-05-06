<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained();
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2);

            // PIX
            $table->text('pix_code')->nullable();
            $table->timestamp('pix_expires_at')->nullable();

            // Boleto
            $table->string('boleto_code')->nullable();
            $table->timestamp('boleto_expires_at')->nullable();

            // Cartão
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->integer('installments')->nullable();

            // Referência do gateway externo
            $table->string('external_reference')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
