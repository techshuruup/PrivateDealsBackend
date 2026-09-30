<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_deals', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_partner_id')->nullable()->after('created_by_seller_id');
            $table->index('created_by_partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_deals', function (Blueprint $table) {
            $table->dropIndex(['created_by_partner_id']);
            $table->dropColumn('created_by_partner_id');
        });
    }
};
