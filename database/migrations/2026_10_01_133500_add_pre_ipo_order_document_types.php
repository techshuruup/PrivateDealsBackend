<?php

use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('type', array_column(DocumentTypeEnum::cases(), 'value'))->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $values = array_values(array_filter(
                array_column(DocumentTypeEnum::cases(), 'value'),
                fn (string $value) => !in_array($value, ['BuyMandate', 'Pre-IPO Share Transfer Receipt'], true)
            ));
            $table->enum('type', $values)->change();
        });
    }
};
