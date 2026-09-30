<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_deals', function (Blueprint $table) {
            $table->decimal('base_price', 15, 2)->nullable()->after('share_price');
        });
    }

    public function down(): void
    {
        Schema::table('company_deals', function (Blueprint $table) {
            $table->dropColumn('base_price');
        });
    }
};
