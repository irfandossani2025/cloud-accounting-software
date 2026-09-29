<?php

namespace App\Support;

/**
 * Oman e-invoicing (Fawtara) reference data from the OpenPeppol PINT OM Billing 1.0.1 specification.
 * https://docs.peppol.eu/poac/om/pint-om/
 */
final class PintOm
{
    public const CUSTOMIZATION_ID = 'urn:peppol:pint:billing-1@om-1';

    public const PROFILE_ID = 'urn:peppol:bis:billing';

    /** Electronic address scheme for the Oman VATIN (EAS 0248). */
    public const ENDPOINT_SCHEME = '0248';

    /** Buyer electronic address used when the buyer has no Oman VATIN (requires the Seller UUID, IBR-173-OM). */
    public const UNKNOWN_BUYER_ENDPOINT = '997770000099';

    /** Transaction type bitmap positions (BTOM-001). */
    public const TX_FULL = 1;

    public const TX_SIMPLIFIED = 2;

    public const TX_EXPORT = 7;

    public const ISSUANCE_REASONS = [
        'CAN' => 'Cancellation or return',
        'VAT' => 'Change in VAT treatment',
        'VAL' => 'Change in value (price, discount)',
        'QTY' => 'Change in quantity',
        'OTH' => 'Other',
    ];

    public const PARTY_ID_SCHEMES = [
        'CR' => 'Commercial Registration',
        'TIN' => 'Tax Identification Number',
        'CID' => 'Civil ID',
        'PASNUM' => 'Passport number',
        'OTHID' => 'Other identifier',
        'ICID' => 'Importer Customs ID',
        'SZLN' => 'Special Zone Licence Number',
    ];

    public const SUBDIVISIONS = [
        'MO' => 'Mainland Oman',
        'SEZAD' => 'Special Economic Zone at Duqm',
        'SHRFZ' => 'Sohar Free Zone',
        'SLLFZ' => 'Salalah Free Zone',
        'AFZ' => 'Al Mazunah Free Zone',
        'OTH' => 'Other',
    ];

    public const ITEM_TYPES = ['G' => 'Goods', 'S' => 'Services'];

    /** Zero-rating reasons (VAT category Z). */
    public const ZERO_RATING = [
        'VATZR-OM-01' => 'Qualifying food items',
        'VATZR-OM-02' => 'Qualifying medicines and medical equipment',
        'VATZR-OM-03' => 'Investment gold, silver and platinum',
        'VATZR-OM-04' => 'International and intra-GCC transport of goods or passengers',
        'VATZR-OM-05' => 'Services related to international / intra-GCC transport',
        'VATZR-OM-06' => 'Qualifying air, sea and land means of transport',
        'VATZR-OM-07' => 'Rescue planes and rescue / assistance boats',
        'VATZR-OM-08' => 'Oil, oil derivatives and natural gas',
        'VATZR-OM-09' => 'Export of services',
        'VATZR-OM-10' => 'Direct export of goods',
        'VATZR-OM-11' => 'Indirect export of goods',
        'VATZR-OM-12' => 'Re-export of goods',
        'VATZR-OM-13' => 'Special Zone to / within Special Zone',
        'VATZR-OM-14' => 'Mainland to Special Zone',
        'VATZR-OM-15' => 'Customs duty suspension to Special Zone',
        'VATZR-OM-16' => 'Special Zone to customs duty suspension',
    ];

    /** Exemption reasons (VAT category E). */
    public const EXEMPTION = [
        'VATEX-OM-01' => 'Qualifying financial services',
        'VATEX-OM-02' => 'Educational services and related goods and services',
        'VATEX-OM-03' => 'Healthcare services and related goods and services',
        'VATEX-OM-04' => 'Rental of residential property',
        'VATEX-OM-05' => 'Local passenger transport',
        'VATEX-OM-06' => 'Undeveloped (bare) land',
        'VATEX-OM-07' => 'Resale of residential property',
        'VATEX-OM-08' => 'Imports for diplomatic / consular bodies and international organisations',
        'VATEX-OM-09' => 'Imports for the Armed Forces and Internal Security Forces',
        'VATEX-OM-10' => 'Imported supplies for non-profit charities',
        'VATEX-OM-11' => 'Import of returned goods',
        'VATEX-OM-12' => 'Imported goods whose supply is exempt or zero-rated',
    ];

    public static function validVatin(?string $vatin): bool
    {
        return (bool) preg_match('/^OM\d{10}$/', (string) $vatin);
    }

    /** Electronic address (EAS 0248) for a VATIN: its ten digits. */
    public static function endpointFor(?string $vatin): ?string
    {
        return self::validVatin($vatin) ? substr($vatin, 2) : null;
    }

    /** 20-character transaction type bitmap with the given positions set. */
    public static function transactionType(int ...$positions): string
    {
        $bits = array_fill(0, 20, '0');
        foreach ($positions as $position) {
            $bits[$position - 1] = '1';
        }

        return implode('', $bits);
    }

    /** RFC 4122 name-based UUID (version 5, SHA-1). */
    public static function uuid5(string $namespace, string $name): string
    {
        $ns = hex2bin(str_replace('-', '', $namespace));
        $hash = sha1($ns.$name);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            '5'.substr($hash, 13, 3),
            dechex((hexdec(substr($hash, 16, 2)) & 0x3F) | 0x80).substr($hash, 18, 2),
            substr($hash, 20, 12),
        );
    }

    /** Seller UUID for a company, derived from its VATIN (stable across installations). */
    public static function sellerUuid(string $vatin): string
    {
        // RFC 4122 URL namespace.
        return self::uuid5('6ba7b811-9dad-11d1-80b4-00c04fd430c8', 'urn:om:vatin:'.$vatin);
    }

    public static function hsDescription(string $code): ?string
    {
        return self::table('hs-codes.tsv')[$code] ?? null;
    }

    public static function isicDescription(string $code): ?string
    {
        return self::table('isic-codes.tsv')[$code] ?? null;
    }

    /**
     * Search a code list by code prefix or words in the description.
     *
     * @return array<string, string>
     */
    public static function search(string $list, string $query, int $limit = 25): array
    {
        $table = self::table($list === 'hs' ? 'hs-codes.tsv' : 'isic-codes.tsv');
        $query = trim(mb_strtolower($query));
        if ($query === '') {
            return [];
        }

        $words = preg_split('/\s+/', $query);
        $results = [];
        foreach ($table as $code => $description) {
            $haystack = $code.' '.mb_strtolower($description);
            if (str_starts_with($code, $query) || array_reduce($words, fn ($ok, $w) => $ok && str_contains($haystack, $w), true)) {
                $results[$code] = $description;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /** @return array<string, string> */
    private static function table(string $file): array
    {
        static $cache = [];

        return $cache[$file] ??= (function () use ($file) {
            $rows = [];
            $handle = fopen(resource_path('data/pint-om/'.$file), 'r');
            while (($line = fgets($handle)) !== false) {
                [$code, $description] = array_pad(explode("\t", rtrim($line, "\n"), 2), 2, '');
                $rows[$code] = $description;
            }
            fclose($handle);

            return $rows;
        })();
    }
}
