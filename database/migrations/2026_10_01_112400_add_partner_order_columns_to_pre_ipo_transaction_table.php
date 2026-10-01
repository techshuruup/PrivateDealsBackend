<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_ipo_transaction', function (Blueprint $table) {
            $table->unsignedBigInteger('deal_id')->nullable()->index()->after('seller_id');
            $table->unsignedBigInteger('partner_id')->nullable()->index()->after('deal_id');
            $table->unsignedBigInteger('seller_investor_id')->nullable()->index()->after('partner_id');
            $table->decimal('base_price', 40, 2)->nullable()->after('distributer_price');
        });
    }

    public function down(): void
    {
        Schema::table('pre_ipo_transaction', function (Blueprint $table) {
            $table->dropIndex(['deal_id']);
            $table->dropIndex(['partner_id']);
            $table->dropIndex(['seller_investor_id']);
            $table->dropColumn(['deal_id', 'partner_id', 'seller_investor_id', 'base_price']);
        });
    }
};
