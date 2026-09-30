<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by_partner_id')->nullable()->after('submitted_by_seller_id');
            $table->index('submitted_by_partner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->dropIndex(['submitted_by_partner_id']);
            $table->dropColumn('submitted_by_partner_id');
        });
    }
};
