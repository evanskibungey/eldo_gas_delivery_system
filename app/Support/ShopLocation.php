<?php

namespace App\Support;

/**
 * Where the shop physically is.
 *
 * Counter sales still have to write delivery_lat/lng — the columns are NOT
 * NULL, and making them nullable would mean auditing every read path, one of
 * which calls .toFixed() on the value and would blank the admin order page.
 *
 * The coordinates are not a placeholder: a walk-in collected the cylinder here,
 * so this genuinely is where the goods changed hands, and a map link on that
 * order correctly points at the shop. `orders.channel` carries the distinction
 * between a collection and a delivery.
 *
 * Reuses the service-area centre rather than introducing a second definition of
 * the same place.
 */
final class ShopLocation
{
    public static function latitude(): float
    {
        return (float) config('delivery.service_area.latitude', 0.5143);
    }

    public static function longitude(): float
    {
        return (float) config('delivery.service_area.longitude', 35.2698);
    }

    public static function label(): string
    {
        return (string) config('delivery.service_area.name', 'Eldoret').' shop counter';
    }
}
