<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotion', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('promotion_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('use_limit')->nullable()->comment('Limite de usos deste produto nesta promoção (null = ilimitado).');
            $table->unsignedInteger('uses_count')->default(0)->comment('Contagem de usos deste produto nesta promoção.');
            $table->primary(['product_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotion');
    }
};
