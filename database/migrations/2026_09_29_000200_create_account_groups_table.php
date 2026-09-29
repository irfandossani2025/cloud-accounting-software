<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('account_groups')->restrictOnDelete();
            // assets | liabilities | income | expenses
            $table->string('nature', 20);
            $table->boolean('affects_gross_profit')->default(false);
            $table->boolean('is_reserved')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_groups');
    }
};
