<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_ipo_transaction', function (Blueprint $table) {
            $table->string('order_step', 64)->nullable()->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pre_ipo_transaction', function (Blueprint $table) {
            $table->dropIndex(['order_step']);
            $table->dropColumn('order_step');
        });
    }
};
