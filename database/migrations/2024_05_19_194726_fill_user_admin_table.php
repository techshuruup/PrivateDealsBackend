<?php

use App\Enums\AdminRoleEnum;
use App\Models\UserAdminModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        UserAdminModel::create([
            'role'                  => 'admin',
            'name'                  => 'PrivateDeals Administrator',
            'username'              => 'private-deals',
            'mobile_no'             => '9867052562',
            'email'                 => 'tech@shuruup.com',
            'password'              => Hash::make('PrivateDeals@123'),
            'ask_password_change'   => '1'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        UserAdminModel::truncate();
    }
};
