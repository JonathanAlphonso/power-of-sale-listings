<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingStatusHistory;
use App\Models\Municipality;
use App\Models\SavedSearch;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedUsers();
        $this->seedListings();
    }

    private function seedUsers(): void
    {
        collect([
            [
                'name' => 'Administrator',
                'email' => 'admin@powerofsale.test',
            ],
            [
                'name' => 'Analyst',
                'email' => 'analyst@powerofsale.test',
            ],
        ])->each(function (array $user): void {
            $account = User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'password',
                ],
            );

            $account->forceFill([
                'email_verified_at' => now(),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        });
    }

    private function seedListings(): void
    {
        $boardSource = Source::query()->firstOrCreate(
            ['slug' => 'treb'],
            [
                'name' => 'Toronto Regional Real Estate Board',
                'type' => 'BOARD',
                'external_identifier' => 'TREB',
                'website_url' => 'https://trreb.ca',
            ],
        );

        $brampton = Municipality::query()->firstOrCreate(
            ['slug' => 'on-brampton'],
            [
                'name' => 'Brampton',
                'province' => 'ON',
                'region' => 'Peel Region',
                'district' => 'Brampton',
                'latitude' => 43.7315,
                'longitude' => -79.7624,
            ],
        );

        if (Listing::query()->doesntExist()) {
            $municipalities = Municipality::factory()->count(3)->create();

            Listing::factory()
                ->count(12)
                ->for($boardSource, 'source')
                ->state(new Sequence(
                    fn (Sequence $sequence) => [
                        'municipality_id' => $municipalities[$sequence->index % max($municipalities->count(), 1)]->id,
                    ],
                ))
                ->has(
                    ListingMedia::factory()
                        ->count(5)
                        ->sequence(
                            fn (Sequence $sequence): array => [
                                'position' => $sequence->index,
                                'is_primary' => $sequence->index === 0,
                            ],
                        ),
                    'media',
                )
                ->create();
        }

        $listing = Listing::upsertFromPayload(
            $this->exampleListingPayload(),
            [
                'source' => $boardSource,
                'municipality' => $brampton,
                'ingestion_batch_id' => 'seed-demo',
            ],
        );

        if (! $listing->statusHistory()->exists()) {
            ListingStatusHistory::factory()
                ->count(3)
                ->for($listing)
                ->for($boardSource)
                ->sequence(
                    ['status_code' => 'NEW', 'status_label' => 'Available', 'changed_at' => now()->subDays(2)],
                    ['status_code' => 'ACTIVE', 'status_label' => 'Active - Marketing', 'changed_at' => now()->subDay()],
                    ['status_code' => 'ACTIVE', 'status_label' => 'Available', 'changed_at' => now()],
                )
                ->create();
        }

        $admin = User::query()->where('email', 'admin@powerofsale.test')->first();

        if ($admin !== null && SavedSearch::query()->where('user_id', $admin->id)->doesntExist()) {
            SavedSearch::factory()
                ->count(2)
                ->for($admin)
                ->create();
        }

        if (SavedSearch::query()->count() === 0) {
            SavedSearch::factory()->count(2)->create();
        }

        if ($admin !== null && AuditLog::query()
            ->where('auditable_type', Listing::class)
            ->where('auditable_id', $listing->id)
            ->doesntExist()) {
            AuditLog::factory()
                ->count(2)
                ->state([
                    'auditable_type' => Listing::class,
                    'auditable_id' => $listing->id,
                    'user_id' => $admin->id,
                ])
                ->create();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleListingPayload(): array
    {
        return [
            '_id' => 'TREB-W12449903',
            'gid' => 'TREB',
            'listingID' => 'W12449903',
            'status' => 'NEW',
            'displayStatus' => 'Available',
            'availability' => 'A',
            'class' => 'CONDO',
            'typeName' => 'Condo Townhouse',
            'style' => '2-Storey',
            'saleOrRent' => 'SALE',
            'currency' => 'CAD',
            'streetNumber' => '9',
            'streetName' => 'Lancewood',
            'streetAddress' => '9 Lancewood Cres',
            'city' => 'Brampton',
            'district' => 'Brampton',
            'neighborhoods' => 'Queen Street Corridor',
            'postalCode' => 'L6S 5Y6',
            'latitude' => 43.710025787353516,
            'longitude' => -79.73492431640625,
            'bedrooms' => 3,
            'bedroomsPossible' => 1,
            'bathrooms' => 4,
            'daysOnMarket' => 16,
            'squareFeet' => '1,400',
            'squareFeetText' => '1400-1599',
            'listPrice' => 3200,
            'originalListPrice' => 3200,
            'price' => 3200,
            'priceLow' => 3200,
            'priceChange' => 0,
            'priceChangeDirection' => 0,
            'displayAddressYN' => 'Y',
            'modified' => '2025-10-13T22:20:09.000Z',
            'imageSets' => [
                [
                    'description' => 'Front exterior',
                    'url' => 'https://live-images.stratuscollab.com/ZxJdIMfbzmv_nD9_6Fc-YiOnigxaucskCqQMXAwKSuQ/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg',
                    'sizes' => [
                        '150' => 'https://live-images.stratuscollab.com/gH_LByrSyCVxC0zpTNjLxKBep7p-dc7g6UUysd7S2k8/rs:fill:150:112:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg',
                        '600' => 'https://live-images.stratuscollab.com/W0pd9sUpT8OUjWJbEJzwjl79QcULsszblsyqfnthHV8/rs:fill:600:400:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg',
                        '900' => 'https://live-images.stratuscollab.com/UmZnbG2SW8GTxW71FR8sB8eLTygIf0O3FhAa0RxJ098/rs:fit:900:600:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg',
                    ],
                ],
                [
                    'description' => 'Kitchen',
                    'url' => 'https://live-images.stratuscollab.com/5Rj8nDwRequSj-BaM6J2XUjO_Ba1HoquIvUKuSYeNFY/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS81ekpyTnpXTVBkR1JmbzhzWlpRMVRhTGFfMTlUcTRBUzVCbnVtaWVlY1NJL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WlRGaU1qVTRZVEl0WVdNMllpMDBNVEF3TFdFeU5EVXRNamsxWXpWak5HSTRZVFZtTG1wd1p3LmpwZw.jpg',
                    'sizes' => [
                        '150' => 'https://live-images.stratuscollab.com/uafIPnerFS4hEHlvzFraeJSz9Ne4Kd-MaiW6aldz55A/rs:fill:150:112:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS81ekpyTnpXTVBkR1JmbzhzWlpRMVRhTGFfMTlUcTRBUzVCbnVtaWVlY1NJL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WlRGaU1qVTRZVEl0WVdNMllpMDBNVEF3TFdFeU5EVXRNamsxWXpWak5HSTRZVFZtTG1wd1p3LmpwZw.jpg',
                        '600' => 'https://live-images.stratuscollab.com/fazQ-FCUI-O6ALvY3dFRug4PxpbURoBcHQGzfJWbmBc/rs:fill:600:400:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS81ekpyTnpXTVBkR1JmbzhzWlpRMVRhTGFfMTlUcTRBUzVCbnVtaWVlY1NJL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WlRGaU1qVTRZVEl0WVdNMllpMDBNVEF3TFdFeU5EVXRNamsxWXpWak5HSTRZVFZtTG1wd1p3LmpwZw.jpg',
                        '900' => 'https://live-images.stratuscollab.com/r_CQ1sHjtx-3VTN-XPp4P2_sCRhkxCTuKMLETmzLpsc/rs:fit:900:600:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS81ekpyTnpXTVBkR1JmbzhzWlpRMVRhTGFfMTlUcTRBUzVCbnVtaWVlY1NJL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WlRGaU1qVTRZVEl0WVdNMllpMDBNVEF3TFdFeU5EVXRNamsxWXpWak5HSTRZVFZtTG1wd1p3LmpwZw.jpg',
                    ],
                ],
                [
                    'description' => 'Living room',
                    'url' => 'https://live-images.stratuscollab.com/0G89J927LfTB_wFw_NT4JQN_vRNOpaw-2cwM9XbiYTE/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9JVHN2VXpULWg3UGFSQzhSNjZ2cTh5ZWMwNlZlX1JwMFU4Sy1WSFZmdmdZL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WVRReU1HVTRaR010TldWall5MDBOMkU0TFdFMk5XVXRZemxsWVdGbVl6bGlaV0V4TG1wd1p3LmpwZw.jpg',
                    'sizes' => [
                        '150' => 'https://live-images.stratuscollab.com/NlOQTws3E3IEFnrCDoBUjTU-nRBLvM6UXiwqaAcYkxg/rs:fill:150:112:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9JVHN2VXpULWg3UGFSQzhSNjZ2cTh5ZWMwNlZlX1JwMFU4Sy1WSFZmdmdZL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WVRReU1HVTRaR010TldWall5MDBOMkU0TFdFMk5XVXRZemxsWVdGbVl6bGlaV0V4TG1wd1p3LmpwZw.jpg',
                        '600' => 'https://live-images.stratuscollab.com/vXHX6P8notO1IppdHMxPq_4PTN49J4_dX_-XBHXghas/rs:fill:600:400:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9JVHN2VXpULWg3UGFSQzhSNjZ2cTh5ZWMwNlZlX1JwMFU4Sy1WSFZmdmdZL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WVRReU1HVTRaR010TldWall5MDBOMkU0TFdFMk5XVXRZemxsWVdGbVl6bGlaV0V4TG1wd1p3LmpwZw.jpg',
                        '900' => 'https://live-images.stratuscollab.com/ZrF6GEfBSEbQjgYJAyoK6k5h-qnM-0FXPLUe5s1d4c4/rs:fit:900:600:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9JVHN2VXpULWg3UGFSQzhSNjZ2cTh5ZWMwNlZlX1JwMFU4Sy1WSFZmdmdZL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WVRReU1HVTRaR010TldWall5MDBOMkU0TFdFMk5XVXRZemxsWVdGbVl6bGlaV0V4TG1wd1p3LmpwZw.jpg',
                    ],
                ],
            ],
            'images' => [
                'https://live-images.stratuscollab.com/ZxJdIMfbzmv_nD9_6Fc-YiOnigxaucskCqQMXAwKSuQ/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg',
                'https://live-images.stratuscollab.com/5Rj8nDwRequSj-BaM6J2XUjO_Ba1HoquIvUKuSYeNFY/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS81ekpyTnpXTVBkR1JmbzhzWlpRMVRhTGFfMTlUcTRBUzVCbnVtaWVlY1NJL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WlRGaU1qVTRZVEl0WVdNMllpMDBNVEF3TFdFeU5EVXRNamsxWXpWak5HSTRZVFZtTG1wd1p3LmpwZw.jpg',
                'https://live-images.stratuscollab.com/0G89J927LfTB_wFw_NT4JQN_vRNOpaw-2cwM9XbiYTE/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9JVHN2VXpULWg3UGFSQzhSNjZ2cTh5ZWMwNlZlX1JwMFU4Sy1WSFZmdmdZL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2WVRReU1HVTRaR010TldWall5MDBOMkU0TFdFMk5XVXRZemxsWVdGbVl6bGlaV0V4TG1wd1p3LmpwZw.jpg',
            ],
        ];
    }
}
