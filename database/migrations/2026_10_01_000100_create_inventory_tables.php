<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('stock_groups')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->timestamps();
        });

        Schema::create('godowns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_reserved')->default(false);
            $table->timestamps();
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->string('alias', 100)->nullable();
            $table->string('part_no', 100)->nullable();
            $table->foreignId('stock_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('vat_category', 20)->nullable();
            $table->foreignId('sales_ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->foreignId('purchase_ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->decimal('sales_rate', 18, 3)->nullable();
            $table->decimal('purchase_rate', 18, 3)->nullable();
            $table->decimal('reorder_level', 15, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Opening stock per godown (books beginning).
        Schema::create('stock_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('godown_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 18, 3);
            $table->decimal('value', 18, 3);
            $table->timestamps();

            $table->unique(['stock_item_id', 'godown_id']);
        });

        // Every change in quantity. quantity: + inward / - outward.
        // value is only meaningful when affects_cost is true (inwards that set the average cost).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('godown_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 18, 3)->default(0);
            $table->decimal('value', 18, 3)->default(0);
            $table->boolean('affects_cost')->default(false);
            // Godown-to-godown transfer: excluded from company-wide inwards/outwards.
            $table->boolean('is_transfer')->default(false);
            $table->timestamps();

            $table->index(['stock_item_id', 'date']);
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('stock_item_id')->nullable()->after('ledger_id')->constrained()->restrictOnDelete();
            $table->foreignId('godown_id')->nullable()->after('stock_item_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['stock_item_id']);
            $table->dropForeign(['godown_id']);
            $table->dropColumn(['stock_item_id', 'godown_id']);
        });
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_openings');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('godowns');
        Schema::dropIfExists('units');
        Schema::dropIfExists('stock_groups');
    }
};
