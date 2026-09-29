<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // contra | payment | receipt | journal | sales | purchase | credit_note | debit_note
            $table->string('base_type', 20);
            $table->string('prefix', 20)->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('is_reserved')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 50);
            $table->date('date');
            $table->string('reference', 100)->nullable();
            $table->date('reference_date')->nullable();
            $table->foreignId('party_ledger_id')->nullable()->constrained('ledgers')->restrictOnDelete();
            $table->text('narration')->nullable();
            $table->decimal('total', 18, 3)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['voucher_type_id', 'number']);
            $table->index('date');
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 18, 3)->default(0);
            $table->decimal('credit', 18, 3)->default(0);
            $table->string('vat_category', 20)->nullable();
            $table->string('narration')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['ledger_id', 'voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_entries');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('voucher_types');
    }
};
