<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnalyticsSetting>
 */
class AnalyticsSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'analytics-'.fake()->unique()->lexify('????????'),
            'enabled' => false,
            'client_enabled' => false,
            'property_id' => null,
            'measurement_id' => null,
            'property_name' => null,
            'service_account_credentials' => null,
            'last_connected_at' => null,
        ];
    }

    public function primary(): self
    {
        return $this->state(fn (): array => [
            'slug' => 'primary',
        ]);
    }

    public function enabled(): self
    {
        return $this->state(fn (): array => [
            'enabled' => true,
        ]);
    }

    public function clientEnabled(): self
    {
        return $this->state(fn (): array => [
            'client_enabled' => true,
            'measurement_id' => 'G-'.strtoupper(fake()->lexify('????###')),
        ]);
    }

    public function configured(): self
    {
        return $this->state(fn (): array => [
            'enabled' => true,
            'property_id' => (string) fake()->numerify('#########'),
            'measurement_id' => 'G-'.strtoupper(fake()->lexify('????###')),
            'property_name' => fake()->company().' Analytics',
            'service_account_credentials' => self::exampleCredentials(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function exampleCredentials(): array
    {
        return [
            'type' => 'service_account',
            'project_id' => 'analytics-sandbox',
            'private_key_id' => 'test-private-key',
            'private_key' => <<<'KEY'
-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCpvOW/Crv+ilQk
/CPW+6aUADmZHgy0/UKj3kz5P8VA/SzTShoHrP700kSJaKUIBg3gYfWalEmCxaIY
QWUC6ZKUiGYQqHTjY2A1DaBdsFhShPLJTsAbF2P1N7j5oVm1GyIWSi88To2G4+Vh
6ZjJ4E+sND36sTnjFNeZ7DoP7KRlN3oAqSJ+v1yABcGDWZdyhH1pfCG7jUR6H6x+
pJQa96oUPHymQN4mA23CILrhV8Xo+nhU5qKelXFPjTrd9Fjw/KXahKGVBN5/drlH
X6pdXq5b2zNQHWT+Z9pRAyxGuUVnfbVWU3nID+FqgSmrdoFpwGgLXg76JIbNXdCY
DyS7ktLlAgMBAAECggEAEsgd5PCHYlBEpM4ImjSA124Z8X0ve0Rt4AuMWWUqyrjp
AZ04uaI1GPp+TnUXk8Z1uR8lydvAMZn0SHpN9s9JIqngH1ZAjtuzbNNr7AoED+d5
pPhTjfvd2ee3TloB/uX4dN5zERmBFAy6GEY8m5P4RO4H8Ko1JVEsVTIcL6BoBXH6
qlk3ZQmX6MGyuJAxLzGkSIdT+L4fwW7MidHduPbjkpv8jPdT+19GI/tQsT1eVRGy
Fmpbm+yvK08f7UCERGjoEDmrMp51XjSYG6F2Qb49vj8zKYOtAcNijyF+07SUYBye
O4fR9xbSgjyZyHT+xUjQviNBiRmK67x+l36RmTQv+QKBgQDRYWLkAi1gqXg5qRj5
Hj0ygHFt3d6fXaKInv8ajIfaZMMG3b5e2QHgegKnIFTzNPFaA6gVhIC2TpnQFRJ4
mqB10NGPAvWck7UQ3MOutjAGXqebvA/CJBmLTrsS11MNcVIsa+aaY8aU4LzGzzR6
uengmDdqrtb//DS1RUMpDCHMLQKBgQDPh+Zh2Z4RUxXHYvh6si/+hR22boEq3i3e
85GdtiE14B5HVideEN6Dre30Yuct31dVObVyZRiH7BX+6l48Mf47mDauMoJ7rYDH
m0L28Rz4cT2adyxbV50Qf2XwkPK50AbTkKReA4XPycq9Y86dzdmwCiwUugq53Mlk
U/cFiKJ8mQKBgQDJU4LrCsznLQzVJKtGnrTpYmeu5K+zPS2TgI560LWwYULFz2HF
gZQ0bB0w5f3I/Rc1Hl74kbfRlDKBykFAhi3UGz3k7UuNitmHpT7jN3tmJI21SVc9
rciCEun+a90IB/ajj/zkZxwC+zWJVKN5flpMAxEGG6fP7Ioh4r95MJku4QKBgQCq
GsSVs+BCZw3U7qSpPWDliIsAO7eYQaDrvE3BLcYu+NMYud9u1PjuiiQfSuoeyZA2
BSVa7M6cqsCkv8oaIQg4JN29Dx2w7lg+RF8xNhT+9yL9d21eOYQ+P455DvZFo+PU
ihyQCuclmEubzTFQW6hxCQV0v8GG8xgIKmKxoHs/EQKBgQDBuzrpraxCcRCyIbyV
7Cfi5aogna4jxlPKZlV//w2SIS55irL932lRv+SlUE4smuryELJ3k2phG8e12Use
jw9wpnMhYeS06udIAPq+EeWc2KiSNqqQJCXjs1QmVIzLcNKKKefUGbHhbX0eT2/e
Prt3FZ+yXK7KBdMMj4PmS/C+jQ==
-----END PRIVATE KEY-----
KEY,
            'client_email' => 'analytics-svc@example.com',
            'client_id' => '1234567890',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];
    }
}
