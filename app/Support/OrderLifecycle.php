<?php

namespace App\Support;

final class OrderLifecycle
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RIDER_ASSIGNED = 'rider_assigned';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_ON_THE_WAY = 'on_the_way';
    public const STATUS_CORRECTION_IN_PROGRESS = 'correction_in_progress';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public static function terminalStatuses(): array
    {
        return [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function activeStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RIDER_ASSIGNED,
            self::STATUS_PICKED_UP,
            self::STATUS_ON_THE_WAY,
            self::STATUS_CORRECTION_IN_PROGRESS,
        ];
    }

    public static function riderBusyStatuses(): array
    {
        return [
            self::STATUS_RIDER_ASSIGNED,
            self::STATUS_PICKED_UP,
            self::STATUS_ON_THE_WAY,
            self::STATUS_CORRECTION_IN_PROGRESS,
        ];
    }

    public static function customerCancellableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RIDER_ASSIGNED,
        ];
    }

    public static function transitionMap(): array
    {
        return [
            self::STATUS_RIDER_ASSIGNED => [self::STATUS_PICKED_UP],
            self::STATUS_PICKED_UP => [self::STATUS_ON_THE_WAY],
            self::STATUS_ON_THE_WAY => [self::STATUS_DELIVERED],
            self::STATUS_CORRECTION_IN_PROGRESS => [self::STATUS_ON_THE_WAY],
        ];
    }

    public static function canTransition(string $current, string $next): bool
    {
        return in_array($next, self::transitionMap()[$current] ?? [], true);
    }

    public static function nextStatus(string $current): ?string
    {
        return self::transitionMap()[$current][0] ?? null;
    }

    public static function isActive(string $status): bool
    {
        return in_array($status, self::activeStatuses(), true);
    }

    public static function canCustomerCancel(string $status): bool
    {
        return in_array($status, self::customerCancellableStatuses(), true);
    }

    public static function canRestoreInventoryOnCancel(string $status): bool
    {
        return in_array($status, self::customerCancellableStatuses(), true);
    }

    public static function trackingStages(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RIDER_ASSIGNED,
            self::STATUS_PICKED_UP,
            self::STATUS_ON_THE_WAY,
            self::STATUS_DELIVERED,
        ];
    }

    public static function normalizeForTracking(string $status): string
    {
        return $status === self::STATUS_CORRECTION_IN_PROGRESS
            ? self::STATUS_ON_THE_WAY
            : $status;
    }

    public static function trackingStageIndex(string $status): int
    {
        $index = array_search(self::normalizeForTracking($status), self::trackingStages(), true);

        return $index === false ? 0 : $index;
    }
}
