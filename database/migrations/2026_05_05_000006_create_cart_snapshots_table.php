<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Array completo de itens no momento do congelamento do carrinho.
            // Cada item: product_id (hashid), product_db_id, name, quantity,
            //             original_price, promotion_id (hashid|null), promotion_name,
            //             applied_discount, final_price
            $table->json('content');

            // O que gerou este snapshot: 'CHECKOUT' | 'ABANDONED_PURGE'
            $table->string('trigger_type');

            $table->decimal('total_value', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_snapshots');
    }
};
