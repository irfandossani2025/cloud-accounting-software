<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->string('alias', 100)->nullable();
            $table->foreignId('account_group_id')->constrained()->restrictOnDelete();
            // Signed: positive = debit, negative = credit.
            $table->decimal('opening_balance', 18, 3)->default(0);
            $table->boolean('is_bill_wise')->default(false);
            $table->boolean('is_reserved')->default(false);
            $table->boolean('is_active')->default(true);

            // Oman VAT: category for sales/purchase/expense ledgers; tax role for Duties & Taxes ledgers.
            $table->string('vat_category', 20)->nullable();
            $table->string('tax_role', 20)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();

            // Party / bank details.
            $table->text('address')->nullable();
            $table->string('vatin', 30)->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('credit_days')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('iban', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
