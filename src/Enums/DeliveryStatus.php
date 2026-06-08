<?php

namespace CleaniqueCoders\ConfigWebhook\Enums;

enum DeliveryStatus: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case RETRYING = 'retrying';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::RETRYING => 'Retrying',
        };
    }

    /**
     * Flux/Tailwind colour key for badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'zinc',
            self::SUCCESS => 'green',
            self::FAILED => 'red',
            self::RETRYING => 'yellow',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
