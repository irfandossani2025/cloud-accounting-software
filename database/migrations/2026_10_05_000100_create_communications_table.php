<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything sent to a customer: invoices, statements and payment reminders.
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained()->nullOnDelete();
            // email | whatsapp
            $table->string('channel', 20);
            // invoice | statement | reminder
            $table->string('kind', 20);
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            // For reminders: the bill reference and the overdue stage (days) it was sent for.
            $table->string('reference', 100)->nullable();
            $table->unsignedSmallInteger('stage')->nullable();
            // sent | failed | opened (WhatsApp: link opened by the user)
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ledger_id', 'kind', 'reference', 'stage']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(false)->after('einvoice_provider');
            // Days overdue at which a reminder goes out, e.g. "3,14,30".
            $table->string('reminder_days', 50)->default('3,14,30')->after('reminders_enabled');
            $table->text('invoice_email_note')->nullable()->after('reminder_days');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['reminders_enabled', 'reminder_days', 'invoice_email_note']);
        });
        Schema::dropIfExists('communications');
    }
};
