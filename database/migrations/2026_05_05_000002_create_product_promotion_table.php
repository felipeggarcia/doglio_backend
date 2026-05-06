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
            $table->primary(['product_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotion');
    }
};
