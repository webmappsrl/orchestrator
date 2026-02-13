<?php

namespace App\Enums;

use Carbon\Carbon;

enum ContractStatus: string
{
    case Expired = 'expired';
    case ExpiringSoon = 'expiring_soon';
    case Active = 'active';
    case NoDate = 'no_date';

    /**
     * Nova Badge style for index/detail display.
     */
    public function badgeStyle(): string
    {
        return match ($this) {
            self::Expired => 'danger',
            self::ExpiringSoon => 'warning',
            self::Active => 'success',
            self::NoDate => 'info',
        };
    }

    /**
     * Translated label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Expired => __('Expired'),
            self::ExpiringSoon => __('Expiring Soon'),
            self::Active => __('Active'),
            self::NoDate => __('No Date'),
        };
    }

    /**
     * Hex color for Nova Partition metric (ContractsByStatus).
     */
    public function metricColor(): string
    {
        return match ($this) {
            self::Expired => '#DC3545',
            self::ExpiringSoon => '#FFC107',
            self::Active => '#28A745',
            self::NoDate => '#17A2B8',
        };
    }

    /**
     * Resolve status from contract expiration date and expiring-soon threshold.
     */
    public static function fromExpirationDate(?string $contractExpirationDate, int $expiringSoonDays): self
    {
        if ($contractExpirationDate === null) {
            return self::NoDate;
        }

        $expirationDate = Carbon::parse($contractExpirationDate);
        $today = Carbon::today();
        $daysUntilExpiration = $today->diffInDays($expirationDate, false);

        if ($daysUntilExpiration < 0) {
            return self::Expired;
        }
        if ($daysUntilExpiration <= $expiringSoonDays) {
            return self::ExpiringSoon;
        }

        return self::Active;
    }
}
