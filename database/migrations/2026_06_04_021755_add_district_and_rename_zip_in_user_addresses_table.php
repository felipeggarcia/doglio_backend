<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_addresses', 'zip')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->renameColumn('zip', 'zip_code');
            });
        }

        if (!Schema::hasColumn('user_addresses', 'district')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->string('district')->nullable()->after('complement');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_addresses', 'zip_code')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->renameColumn('zip_code', 'zip');
            });
        }

        if (Schema::hasColumn('user_addresses', 'district')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->dropColumn('district');
            });
        }
    }
};
