<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // No voucher dated on or before this date can be created, altered or cancelled.
            $table->date('locked_until')->nullable()->after('books_begin_from');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // created | altered | cancelled | deleted | locked | login ...
            $table->string('action', 20);
            $table->string('subject_type', 50);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500);
            // JSON text (MariaDB 10.1 has no JSON column type).
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('locked_until');
        });
    }
};
