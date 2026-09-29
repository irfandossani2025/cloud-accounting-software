<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->timestamps();
        });

        // OMR value of one unit of the currency on a date.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 18, 6);
            $table->timestamps();

            $table->unique(['currency_id', 'date']);
        });

        Schema::create('cost_centres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centres')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->boolean('cost_centres_applicable')->default(false)->after('is_bill_wise');
            $table->foreignId('currency_id')->nullable()->after('cost_centres_applicable')->constrained()->nullOnDelete();
            // Opening balance in the ledger's foreign currency (signed + Dr / - Cr).
            $table->decimal('opening_fx_balance', 18, 3)->nullable()->after('currency_id');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('due_date')->constrained()->restrictOnDelete();
            $table->decimal('fx_rate', 18, 6)->nullable()->after('currency_id');
        });

        Schema::table('voucher_entries', function (Blueprint $table) {
            // Bank instrument details and reconciliation.
            $table->string('instrument_type', 20)->nullable()->after('vat_category');
            $table->string('instrument_no', 50)->nullable()->after('instrument_type');
            $table->date('instrument_date')->nullable()->after('instrument_no');
            $table->date('bank_date')->nullable()->after('instrument_date');
            // Foreign currency amount (signed like the entry: + debit / - credit) and rate.
            $table->foreignId('currency_id')->nullable()->after('bank_date')->constrained()->restrictOnDelete();
            $table->decimal('fx_amount', 18, 3)->nullable()->after('currency_id');
            $table->decimal('fx_rate', 18, 6)->nullable()->after('fx_amount');
        });

        // Amount signed + debit / - credit, like the entry it splits.
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_centre_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 3);
            $table->timestamps();

            $table->index('cost_centre_id');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('cost_centre_id')->nullable()->after('godown_id')->constrained()->restrictOnDelete();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('from_date');
            $table->date('to_date');
            $table->timestamps();
        });

        // Amount in the natural direction of the account: expenses/assets as debit, income/liabilities as credit.
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('account_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['cost_centre_id']);
            $table->dropColumn('cost_centre_id');
        });
        Schema::dropIfExists('cost_allocations');
        Schema::table('voucher_entries', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['instrument_type', 'instrument_no', 'instrument_date', 'bank_date', 'currency_id', 'fx_amount', 'fx_rate']);
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'fx_rate']);
        });
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['cost_centres_applicable', 'currency_id', 'opening_fx_balance']);
        });
        Schema::dropIfExists('cost_centres');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
