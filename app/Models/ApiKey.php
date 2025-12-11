<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    public const SLUG_MAPTILER = 'maptiler';

    public const SLUG_GOOGLE = 'google';

    public const SLUG_DISCORD = 'discord';

    public const SLUG_FACEBOOK = 'facebook';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'api_key',
        'api_secret',
        'additional_config',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'additional_config' => AsEncryptedArrayObject::class,
            'is_enabled' => 'boolean',
        ];
    }

    public static function getBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }

    public static function getOrCreate(string $slug, string $name, ?string $description = null): self
    {
        return static::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'is_enabled' => false,
            ]
        );
    }

    public static function maptiler(): self
    {
        return static::getOrCreate(
            self::SLUG_MAPTILER,
            'MapTiler',
            'Map tile provider for property maps'
        );
    }

    public static function google(): self
    {
        return static::getOrCreate(
            self::SLUG_GOOGLE,
            'Google OAuth',
            'Google OAuth for social login'
        );
    }

    public static function discord(): self
    {
        return static::getOrCreate(
            self::SLUG_DISCORD,
            'Discord OAuth',
            'Discord OAuth for social login'
        );
    }

    public static function facebook(): self
    {
        return static::getOrCreate(
            self::SLUG_FACEBOOK,
            'Facebook OAuth',
            'Facebook OAuth for social login'
        );
    }

    public static function allConfigured(): array
    {
        return [
            'maptiler' => static::maptiler(),
            'google' => static::google(),
            'discord' => static::discord(),
            'facebook' => static::facebook(),
        ];
    }

    public function isConfigured(): bool
    {
        return $this->is_enabled && $this->api_key !== null && $this->api_key !== '';
    }

    public function hasSecret(): bool
    {
        return $this->api_secret !== null && $this->api_secret !== '';
    }

    public function getMaskedKey(): ?string
    {
        if (! $this->api_key) {
            return null;
        }

        $key = $this->api_key;
        $length = strlen($key);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($key, 0, 4) . str_repeat('*', $length - 8) . substr($key, -4);
    }

    public function getMaskedSecret(): ?string
    {
        if (! $this->api_secret) {
            return null;
        }

        $secret = $this->api_secret;
        $length = strlen($secret);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 4) . str_repeat('*', $length - 8) . substr($secret, -4);
    }
}
