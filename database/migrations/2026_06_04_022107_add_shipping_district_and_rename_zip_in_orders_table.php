<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'shipping_zip')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('shipping_zip', 'shipping_zip_code');
            });
        }

        if (!Schema::hasColumn('orders', 'shipping_district')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_district')->nullable()->after('shipping_complement');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'shipping_zip_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('shipping_zip_code', 'shipping_zip');
            });
        }

        if (Schema::hasColumn('orders', 'shipping_district')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('shipping_district');
            });
        }
    }
};
