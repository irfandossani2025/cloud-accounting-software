<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oman e-invoicing (Fawtara, Peppol PINT OM Billing 1.0.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Structured address. PINT OM IBR-010-OM makes all five parts mandatory for the seller:
            // street, additional street (area), city, postal code and a third line (P.O. Box).
            $table->string('street')->nullable()->after('address_ar');
            $table->string('additional_street')->nullable()->after('street');
            $table->string('po_box', 50)->nullable()->after('additional_street');
            $table->string('city', 100)->nullable()->after('po_box');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->string('country_subdivision', 10)->default('MO')->after('postal_code');
            $table->boolean('einvoicing_enabled')->default(false)->after('vat_registered');
            // Seller UUID (BTOM-004); also the namespace for invoice UUIDs.
            $table->char('einvoice_seller_uuid', 36)->nullable()->after('einvoicing_enabled');
            // manual = export XML and upload in the service provider's portal.
            $table->string('einvoice_provider', 30)->default('manual')->after('einvoice_seller_uuid');
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->string('street')->nullable()->after('address');
            $table->string('additional_street')->nullable()->after('street');
            $table->string('po_box', 50)->nullable()->after('additional_street');
            $table->string('city', 100)->nullable()->after('po_box');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->char('country_code', 2)->default('OM')->after('postal_code');
            $table->string('country_subdivision', 10)->nullable()->after('country_code');
            // Buyer identifier (IBT-046) with scheme CR / TIN / CID / PASNUM / OTHID / ICID / SZLN.
            $table->string('party_id_scheme', 10)->nullable()->after('cr_number');
            $table->string('party_id', 50)->nullable()->after('party_id_scheme');
            // Defaults for invoice lines posted to this sales ledger.
            $table->char('item_type', 1)->nullable()->after('vat_rate');
            $table->string('hs_code', 12)->nullable()->after('item_type');
            $table->string('isic_code', 6)->nullable()->after('hs_code');
            $table->string('exemption_code', 20)->nullable()->after('isic_code');
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->char('item_type', 1)->default('G')->after('vat_category');
            $table->string('hs_code', 12)->nullable()->after('item_type');
            $table->string('isic_code', 6)->nullable()->after('hs_code');
            $table->string('exemption_code', 20)->nullable()->after('isic_code');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            // Credit notes: the invoice being corrected and why (IBR-023-OM, IBR-032-OM).
            $table->foreignId('original_voucher_id')->nullable()->after('fx_rate')->constrained('vouchers')->nullOnDelete();
            $table->string('issuance_reason', 3)->nullable()->after('original_voucher_id');
        });

        Schema::create('einvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();
            $table->char('transaction_type', 20);
            // draft | submitted | accepted | rejected
            $table->string('status', 20)->default('draft');
            $table->longText('xml')->nullable();
            $table->char('xml_sha256', 64)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einvoices');
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['original_voucher_id']);
            $table->dropColumn(['original_voucher_id', 'issuance_reason']);
        });
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'hs_code', 'isic_code', 'exemption_code']);
        });
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn(['street', 'additional_street', 'po_box', 'city', 'postal_code', 'country_code', 'country_subdivision',
                'party_id_scheme', 'party_id', 'item_type', 'hs_code', 'isic_code', 'exemption_code']);
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['street', 'additional_street', 'po_box', 'city', 'postal_code', 'country_subdivision',
                'einvoicing_enabled', 'einvoice_seller_uuid', 'einvoice_provider']);
        });
    }
};
