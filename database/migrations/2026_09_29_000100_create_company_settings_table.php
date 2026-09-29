<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('address')->nullable();
            $table->text('address_ar')->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->string('vatin', 30)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->char('currency', 3)->default('OMR');
            $table->unsignedTinyInteger('decimals')->default(3);
            $table->date('financial_year_start');
            $table->date('books_begin_from');
            $table->boolean('vat_registered')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
