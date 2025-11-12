# Power-of-Sale Detection & OData Replication (Laravel)  
*(for AMPRE/PropTx Web API via OData)*

> **TL;DR:** The feed treats `PublicRemarks` as **NotFilterable** at `$filter` time for certain boards (e.g., `X12355617`). Therefore, **do not** use `contains(PublicRemarks, ...)` on the server. Replicate “For Sale” listings, **page via `@odata.nextLink`**, and detect “Power of Sale” **client-side** with Unicode-robust normalization + regex. Use a **watermark** on `SystemModificationTimestamp` for deltas.

---

## References (quick)
- 📜 OData paging `@odata.nextLink` 🔎 [nextLink basics](https://www.google.com/search?q=%40odata.nextLink)  
- 🔧 Stable `$orderby` for paging 🔎 [$orderby stable pagination](https://www.google.com/search?q=OData+%24orderby+stable+paging)  
- 🔎 OData Capabilities: NotFilterableProperties 🔎 [FilterRestrictions](https://www.google.com/search?q=OData+Capabilities+FilterRestrictions+NotFilterableProperties)  
- 🔤 Unicode pitfalls (NBSP/ZWSP/dashes) 🔎 [Unicode NBSP](https://www.google.com/search?q=unicode+NBSP+U%2B00A0) • 🔎 [Zero-width chars](https://www.google.com/search?q=zero+width+space+U%2B200B) • 🔎 [Confusables](https://www.google.com/search?q=unicode+confusables)

---

## Problem Statement

- The endpoint returns `PublicRemarks` in the projection but evaluates it as **`null` at filter time** for some records/boards.  
- Example:  
  - `?$filter=ListingKey eq 'X12355617'&$select=ListingKey,PublicRemarks` → **returns row with remarks**  
  - `?$filter=ListingKey eq 'X12355617' and PublicRemarks eq null` → **also returns row**  
- Consequence: Any server-side phrase search like `contains(PublicRemarks,'Power of Sale')` is unreliable.

**Solution:** Replicate “For Sale” listings, then classify **Power-of-Sale** in your Laravel app after robust text normalization.

---

## Environment & Config

**.env**
```
AMPRE_BASE=https://query.ampre.ca/odata/Property
AMPRE_TOKEN=<<YOUR_BEARER_TOKEN>>
AMPRE_PAGE_SIZE=300
```

Ensure PHP `intl` extension is installed (for Unicode normalization). If missing, the code below still works but with slightly reduced robustness.

---

## Database Changes

### 1) Cursor table (replication watermark)
```php
// database/migrations/2025_11_11_000001_create_odata_cursors_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('odata_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 128)->unique(); // e.g., "Property.ForSale"
            $table->timestampTz('last_system_mod_ts')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('odata_cursors');
    }
};
```

### 2) Listings table: add POS fields (adjust names to your schema)
```php
// database/migrations/2025_11_11_000002_add_pos_fields_to_listings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'listing_key')) {
                $table->string('listing_key', 32)->nullable()->index();
            }
            if (!Schema::hasColumn('listings', 'transaction_type')) {
                $table->string('transaction_type', 32)->nullable()->index();
            }
            if (!Schema::hasColumn('listings', 'public_remarks')) {
                $table->longText('public_remarks')->nullable();
            }
            if (!Schema::hasColumn('listings', 'remarks_normalized')) {
                $table->longText('remarks_normalized')->nullable();
            }
            if (!Schema::hasColumn('listings', 'is_power_of_sale')) {
                $table->boolean('is_power_of_sale')->default(false)->index();
            }
            if (!Schema::hasColumn('listings', 'pos_detected_at')) {
                $table->timestampTz('pos_detected_at')->nullable()->index();
            }
            if (!Schema::hasColumn('listings', 'system_modification_timestamp')) {
                $table->timestampTz('system_modification_timestamp')->nullable()->index();
            }
        });
    }
    public function down(): void {
        Schema::table('listings', function (Blueprint $table) {
            // reverse cautiously if needed
        });
    }
};
```

> **Mapping note:** If you already store an external ID, map OData `ListingKey` → your `external_id` and keep `listing_key` only if helpful.

---

## Text Normalization & Detection (robust to Unicode)

```php
// app/Support/PosDetection.php
<?php

namespace App\Support;

class PosDetection
{
    public static function normalize(string $s): string
    {
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C) ?? $s;
        }
        // Remove control + zero-width
        $s = preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{2028}-\x{202E}\x{2060}\x{FEFF}]/u', '', $s);
        // Common confusables → ASCII
        $s = strtr($s, [
            'а' => 'a','е' => 'e','о' => 'o','с' => 'c','і' => 'i','ӏ' => 'l','İ' => 'i','ı' => 'i',
        ]);
        // All Unicode spaces → space
        $s = preg_replace('/\p{Z}+/u', ' ', $s);
        // All dashes/hyphens → "-"
        $s = preg_replace('/[\p{Pd}‐-‒–—―-]+/u', '-', $s);
        // Collapse whitespace, lowercase
        $s = mb_strtolower(preg_replace('/\s+/u', ' ', $s), 'UTF-8');
        return trim($s);
    }

    public static function hasPowerOfSale(?string $remarks): bool
    {
        if (!$remarks) return false;
        $n = self::normalize($remarks);
        // match "power [ -]* of [ -]* sale"
        return (bool) preg_match('/\bpower(?:[ -])*of(?:[ -])*sale\b/u', $n);
    }
}
```

---

## Replication Service (pull + paginate + classify)

```php
// app/Services/ODataReplicator.php
<?php

namespace App\Services;

use App\Support\PosDetection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ODataReplicator
{
    private string $base;
    private string $token;
    private int $pageSize;

    public function __construct()
    {
        $this->base = rtrim(config('services.ampre.base', env('AMPRE_BASE')), '/');
        $this->token = env('AMPRE_TOKEN');
        $this->pageSize = (int) env('AMPRE_PAGE_SIZE', 300);
    }

    /**
     * Replicate "For Sale" listings changed since optional $since (UTC ISO8601).
     * Returns [collectedCount, upsertedCount, posCount].
     */
    public function replicate(?CarbonImmutable $since = null): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $params = [
            '$count'   => 'true',
            '$top'     => (string) $this->pageSize,
            '$orderby' => 'SystemModificationTimestamp desc',
            '$select'  => 'ListingKey,TransactionType,PublicRemarks,SystemModificationTimestamp,UnparsedAddress,PropertyType,StandardStatus',
            '$filter'  => "TransactionType eq 'For Sale'".
                          ($since ? " and SystemModificationTimestamp ge ".$since->toIso8601String() : ''),
        ];

        $collected = 0;
        $upserts = 0;
        $posCount = 0;
        $maxTs = $since;

        // Initial fetch
        $resp = Http::withHeaders($headers)->get($this->base, $params);
        $data = $resp->json();

        $process = function (array $batch) use (&$collected, &$upserts, &$posCount, &$maxTs) {
            $rows = collect($batch);

            $toUpsert = $rows->map(function ($r) use (&$posCount, &$maxTs) {
                $remarks = $r['PublicRemarks'] ?? null;
                $normalized = $remarks ? \App\Support\PosDetection::normalize($remarks) : null;
                $isPos = \App\Support\PosDetection::hasPowerOfSale($remarks);
                if ($isPos) $posCount++;

                // Track watermark
                if (!empty($r['SystemModificationTimestamp'])) {
                    $ts = CarbonImmutable::parse($r['SystemModificationTimestamp']);
                    if (!$maxTs || $ts->greaterThan($maxTs)) {
                        $maxTs = $ts;
                    }
                }

                return [
                    // Map to your schema as needed:
                    'listing_key'                    => $r['ListingKey'] ?? null,
                    'transaction_type'               => $r['TransactionType'] ?? null,
                    'public_remarks'                 => $remarks,
                    'remarks_normalized'             => $normalized,
                    'is_power_of_sale'               => $isPos,
                    'pos_detected_at'                => $isPos ? now() : null,
                    'system_modification_timestamp'  => !empty($r['SystemModificationTimestamp'])
                        ? CarbonImmutable::parse($r['SystemModificationTimestamp'])
                        : null,
                    // Extras (example)
                    'unparsed_address'               => $r['UnparsedAddress'] ?? null,
                    'property_type'                  => $r['PropertyType'] ?? null,
                    'standard_status'                => $r['StandardStatus'] ?? null,
                    'updated_at'                     => now(),
                    'created_at'                     => now(),
                ];
            });

            $collected += $rows->count();

            // Upsert by listing_key (adjust unique key to your schema)
            $upserts += DB::table('listings')->upsert(
                $toUpsert->all(),
                uniqueBy: ['listing_key'],
                update: [
                    'transaction_type',
                    'public_remarks',
                    'remarks_normalized',
                    'is_power_of_sale',
                    'pos_detected_at',
                    'system_modification_timestamp',
                    'unparsed_address',
                    'property_type',
                    'standard_status',
                    'updated_at',
                ]
            );
        };

        $process($data['value'] ?? []);
        while (isset($data['@odata.nextLink'])) {
            $resp = Http::withHeaders($headers)->get($data['@odata.nextLink']);
            $data = $resp->json();
            $process($data['value'] ?? []);
        }

        // Save watermark
        if ($maxTs) {
            DB::table('odata_cursors')->updateOrInsert(
                ['resource' => 'Property.ForSale'],
                ['last_system_mod_ts' => $maxTs, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return [$collected, $upserts, $posCount];
    }
}
```

---

## Artisan Command (manual + scheduled)

```php
// app/Console/Commands/ReplicatePosListings.php
<?php

namespace App\Console\Commands;

use App\Services\ODataReplicator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplicatePosListings extends Command
{
    protected $signature = 'pos:replicate {--since=}';
    protected $description = 'Replicate For Sale listings and detect Power of Sale client-side';

    public function handle(ODataReplicator $replicator): int
    {
        $sinceOpt = $this->option('since');
        $since = null;

        if ($sinceOpt) {
            $since = CarbonImmutable::parse($sinceOpt);
        } else {
            $row = DB::table('odata_cursors')->where('resource', 'Property.ForSale')->first();
            if ($row && $row->last_system_mod_ts) {
                $since = CarbonImmutable::parse($row->last_system_mod_ts);
            }
        }

        [$collected, $upserts, $posCount] = $replicator->replicate($since);

        $this->info("Collected: {$collected}, Upserts: {$upserts}, POS detected: {$posCount}");
        return self::SUCCESS;
    }
}
```

**Schedule** (every 10 mins; tune as needed):
```php
// app/Console/Kernel.php
protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('pos:replicate')->everyTenMinutes()->withoutOverlapping();
}
```

---

## Minimal HTTP Strategy (for sanity checks)

- Fetch **all** “For Sale” rows without remark filters:
```
Property?$count=true&$top=300&
$orderby=SystemModificationTimestamp%20desc&
$select=ListingKey,TransactionType,PublicRemarks,SystemModificationTimestamp&
$filter=TransactionType%20eq%20'For%20Sale'
```

- Spot-check specific keys to confirm remarks exist even when filters misbehave:
```
Property?$filter=ListingKey%20eq%20'X12355617'&$select=ListingKey,PublicRemarks
```

> **Reminder:** Do not depend on `$filter` with `contains()` for production logic; always pull the full delta and classify locally.

---

## Tests (Pest)

```php
// tests/Unit/PosDetectionTest.php
<?php

use App\Support\PosDetection;

it('detects power of sale variations', function () {
    expect(PosDetection::hasPowerOfSale('under Power of Sale'))->toBeTrue();
    expect(PosDetection::hasPowerOfSale('under Power-of-Sale'))->toBeTrue(); // ASCII hyphen
    expect(PosDetection::hasPowerOfSale('under Power–of–Sale'))->toBeTrue(); // en dash
    expect(PosDetection::hasPowerOfSale("under Power\u{00A0}of\u{00A0}Sale"))->toBeTrue(); // NBSP
    expect(PosDetection::hasPowerOfSale('POWER OF SALE'))->toBeTrue();
    expect(PosDetection::hasPowerOfSale('This is not relevant'))->toBeFalse();
});
```

```php
// tests/Feature/ReplicatorBuildsUpsertsTest.php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('pages through nextLink and upserts', function () {
    Http::fakeSequence()
        ->push([
            '@odata.context' => '$metadata#Property',
            'value' => [
                [
                    'ListingKey' => 'X1',
                    'TransactionType' => 'For Sale',
                    'PublicRemarks' => 'as is where is under Power of Sale',
                    'SystemModificationTimestamp' => '2025-10-01T00:00:00Z',
                ],
            ],
            '@odata.nextLink' => 'https://query.ampre.ca/odata/Property?$skip=300',
        ], 200)
        ->push([
            '@odata.context' => '$metadata#Property',
            'value' => [
                [
                    'ListingKey' => 'X2',
                    'TransactionType' => 'For Sale',
                    'PublicRemarks' => 'regular listing',
                    'SystemModificationTimestamp' => '2025-10-02T00:00:00Z',
                ],
            ],
        ], 200);

    // run command
    \Illuminate\Support\Facades\Artisan::call('pos:replicate');

    $x1 = DB::table('listings')->where('listing_key', 'X1')->first();
    $x2 = DB::table('listings')->where('listing_key', 'X2')->first();

    expect($x1->is_power_of_sale)->toBeTrue();
    expect($x2->is_power_of_sale)->toBeFalse();

    // cursor saved
    $cursor = DB::table('odata_cursors')->where('resource', 'Property.ForSale')->first();
    expect($cursor)->not()->toBeNull();
});
```

---

## Operational Guidance

- **Never** rely on `contains(PublicRemarks, …)` server-side for PoS. Treat server text filters as **advisory only**.  
- Always set a **deterministic `$orderby`** (e.g., `SystemModificationTimestamp desc`) when paging; **follow `@odata.nextLink`** until exhausted.  
- Use a **watermark** (last `SystemModificationTimestamp`) from `odata_cursors` to fetch only deltas.  
- **Idempotency:** upsert on a unique key (`listing_key` or your `external_id`).  
- **Observability:** log counts and latest watermark per run; alert if a run yields 0 rows for an extended period.  
- **Token handling:** on `401/403`, refresh or rotate `AMPRE_TOKEN`. On `429/5xx`, apply exponential backoff.

---

## Optional: Provider Ticket Template

> **Subject:** `PublicRemarks` not filterable on `Property` (board: Kingston & Area)  
> **Repro:**  
> - `Property?$filter=ListingKey eq 'X12355617'&$select=ListingKey,PublicRemarks` → returns row with non-null `PublicRemarks`.  
> - `Property?$filter=ListingKey eq 'X12355617' and PublicRemarks eq null` → returns same row.  
> - `Property?$filter=ListingKey eq 'X12355617' and contains(PublicRemarks,'Sale')` → returns 0 rows.  
> **Expected:** filter evaluation should align with projection or `PublicRemarks` should be surfaced in `$metadata` Capabilities as **NotFilterable**.  
> **Impact:** cannot server-filter for “Power of Sale”; must replicate and classify client-side.

---

## Ready-Made Tasks for Codex (copy/paste)

1) **Create migrations** for `odata_cursors` and add PoS fields to `listings`.  
2) **Create** `app/Support/PosDetection.php` (above).  
3) **Create** `app/Services/ODataReplicator.php` (above).  
4) **Create** `app/Console/Commands/ReplicatePosListings.php` and register in `Kernel`.  
5) **Run**: `php artisan migrate && php artisan pos:replicate --since="2025-01-01T00:00:00Z"`  
6) **Add tests** (`PosDetectionTest`, `ReplicatorBuildsUpsertsTest`) and run `php artisan test`.  
7) **Set scheduler** to `everyTenMinutes()` (or Horizon job if you prefer queues).  
8) **Expose** an admin toggle/filter on `is_power_of_sale = 1`.

---

## FAQ

- **Can I prefilter server-side to reduce bandwidth?**  
  Limited. You can safely filter by **structured** fields (e.g., `TransactionType`, timestamps), but not by `PublicRemarks`.  

- **Why normalization?**  
  Because remarks often contain NBSPs, dashes, or confusables that make simple contains checks brittle. The regex on normalized text is resilient.

- **What about `$search`?**  
  If the service exposes OData full-text `$search` (not standard everywhere), consider testing it, but don’t depend on it. 🔎 [OData $search](https://www.google.com/search?q=OData+%24search+full+text)

---

**Done.** This spec is production-oriented, copy-paste ready, and aligned with the observed provider behavior.
