<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Subscriber = 'subscriber';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Subscriber => __('Subscriber'),
        };
    }
}
