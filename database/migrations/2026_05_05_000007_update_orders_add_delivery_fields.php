<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove o campo legado pix_code da tabela orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pix_code');
        });

        // Adiciona suporte a entrega com endereço e snapshot do endereço
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('user_addresses')
                ->nullOnDelete()
                ->after('user_id');
            $table->enum('delivery_type', ['delivery', 'pickup'])->after('status');
            $table->string('shipping_street')->nullable()->after('delivery_type');
            $table->string('shipping_number')->nullable()->after('shipping_street');
            $table->string('shipping_complement')->nullable()->after('shipping_number');
            $table->string('shipping_city')->nullable()->after('shipping_complement');
            $table->string('shipping_state', 2)->nullable()->after('shipping_city');
            $table->string('shipping_zip', 8)->nullable()->after('shipping_state');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropColumn([
                'address_id',
                'delivery_type',
                'shipping_street',
                'shipping_number',
                'shipping_complement',
                'shipping_city',
                'shipping_state',
                'shipping_zip',
            ]);
            $table->text('pix_code')->nullable();
        });
    }
};
