<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('is_invoice')->default(false)->after('party_ledger_id');
            $table->date('due_date')->nullable()->after('is_invoice');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            // Sales / purchase (or expense) ledger the line is posted to.
            $table->foreignId('ledger_id')->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->string('description_ar', 500)->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('rate', 18, 3)->default(0);
            $table->decimal('discount', 18, 3)->default(0);
            $table->decimal('amount', 18, 3)->default(0);
            $table->string('vat_category', 20);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 18, 3)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Bill-by-bill tracking for party ledgers. Amount is signed: + debit, - credit.
        Schema::create('bill_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            // Null for opening bills entered on the ledger master.
            $table->foreignId('voucher_id')->nullable()->constrained()->cascadeOnDelete();
            // new_ref | against_ref | advance | on_account
            $table->string('type', 20);
            $table->string('reference', 100);
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 18, 3);
            $table->timestamps();

            $table->index(['ledger_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_allocations');
        Schema::dropIfExists('invoice_lines');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['is_invoice', 'due_date']);
        });
    }
};
