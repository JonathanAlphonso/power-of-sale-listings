<?php

namespace App\Models;

use ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsSetting extends Model
{
    /** @use HasFactory<\Database\Factories\AnalyticsSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'enabled',
        'client_enabled',
        'property_id',
        'measurement_id',
        'property_name',
        'service_account_credentials',
        'last_connected_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $setting): void {
            $setting->slug ??= 'primary';
        });
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([
            'slug' => 'primary',
        ]);
    }

    public function isConfigured(): bool
    {
        $credentials = $this->service_account_credentials;

        if ($credentials instanceof ArrayObject) {
            $credentials = $credentials->getArrayCopy();
        }

        if (! is_array($credentials)) {
            return false;
        }

        return $this->enabled
            && is_string($this->property_id) && $this->property_id !== ''
            && array_key_exists('client_email', $credentials)
            && array_key_exists('private_key', $credentials);
    }

    public function markConnected(): void
    {
        $this->forceFill([
            'last_connected_at' => now(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'client_enabled' => 'bool',
            'service_account_credentials' => AsEncryptedArrayObject::class,
            'last_connected_at' => 'datetime',
        ];
    }

    public function clientTrackingEnabled(): bool
    {
        return $this->client_enabled && $this->clientMeasurementId() !== null;
    }

    public function clientMeasurementId(): ?string
    {
        $measurementId = is_string($this->measurement_id) ? trim($this->measurement_id) : '';

        return $measurementId !== '' ? $measurementId : null;
    }

    public function serverTrackingEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    public static function clientSetting(): ?self
    {
        return static::query()
            ->select(['id', 'slug', 'client_enabled', 'measurement_id'])
            ->where('client_enabled', true)
            ->whereNotNull('measurement_id')
            ->first();
    }
}
