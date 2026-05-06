<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_on_restock')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'product_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
