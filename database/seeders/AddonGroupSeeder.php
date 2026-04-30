<?php

namespace Database\Seeders;

use App\Models\AddonGroup;
use App\Models\AddonItem;
use Illuminate\Database\Seeder;

class AddonGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Keyed by size_id so groups only appear for the relevant cylinder size.
        // firstOrCreate on [size_id, name] makes this safe to re-run and won't
        // clash with groups an admin creates later through the admin panel.
        $definitions = [
            // ── 3 kg (size_id 1) ────────────────────────────────────────────
            1 => [
                [
                    'name'           => 'Regulator',
                    'selection_type' => 'single',
                    'sort_order'     => 1,
                    'items'          => [
                        ['name' => 'Standard Regulator',    'description' => 'Fits all standard 3 kg cylinders',  'price' => 350,  'sort_order' => 1],
                        ['name' => 'Heavy-Duty Regulator',  'description' => 'Reinforced body, longer lifespan',  'price' => 550,  'sort_order' => 2],
                    ],
                ],
                [
                    'name'           => 'Hosepipe',
                    'selection_type' => 'single',
                    'sort_order'     => 2,
                    'items'          => [
                        ['name' => '1.5 m Hosepipe', 'description' => 'Standard household length', 'price' => 200, 'sort_order' => 1],
                        ['name' => '2 m Hosepipe',   'description' => 'Extra reach for tight corners', 'price' => 270, 'sort_order' => 2],
                    ],
                ],
                [
                    'name'           => 'Extras',
                    'selection_type' => 'multi',
                    'sort_order'     => 3,
                    'items'          => [
                        ['name' => 'Piezo Lighter',    'description' => 'Long-neck igniter for gas burners',  'price' => 120, 'sort_order' => 1],
                        ['name' => 'Hose Clamp (x2)', 'description' => 'Stainless steel pipe clamps',        'price' => 80,  'sort_order' => 2],
                    ],
                ],
            ],

            // ── 6 kg (size_id 2) ────────────────────────────────────────────
            2 => [
                [
                    'name'           => 'Regulator',
                    'selection_type' => 'single',
                    'sort_order'     => 1,
                    'items'          => [
                        ['name' => 'Standard Regulator',   'description' => 'Fits all standard 6 kg cylinders', 'price' => 350, 'sort_order' => 1],
                        ['name' => 'Heavy-Duty Regulator', 'description' => 'Reinforced body, longer lifespan', 'price' => 550, 'sort_order' => 2],
                    ],
                ],
                [
                    'name'           => 'Hosepipe',
                    'selection_type' => 'single',
                    'sort_order'     => 2,
                    'items'          => [
                        ['name' => '1.5 m Hosepipe', 'description' => 'Standard household length',    'price' => 200, 'sort_order' => 1],
                        ['name' => '2 m Hosepipe',   'description' => 'Extra reach for tight corners','price' => 270, 'sort_order' => 2],
                    ],
                ],
                [
                    'name'           => 'Extras',
                    'selection_type' => 'multi',
                    'sort_order'     => 3,
                    'items'          => [
                        ['name' => 'Piezo Lighter',    'description' => 'Long-neck igniter for gas burners', 'price' => 120, 'sort_order' => 1],
                        ['name' => 'Hose Clamp (x2)', 'description' => 'Stainless steel pipe clamps',       'price' => 80,  'sort_order' => 2],
                    ],
                ],
            ],

            // ── 13 kg (size_id 3) ───────────────────────────────────────────
            3 => [
                [
                    'name'           => 'Regulator',
                    'selection_type' => 'single',
                    'sort_order'     => 1,
                    'items'          => [
                        ['name' => 'Standard Regulator',   'description' => 'Fits all standard 13 kg cylinders', 'price' => 400, 'sort_order' => 1],
                        ['name' => 'Heavy-Duty Regulator', 'description' => 'Reinforced body, longer lifespan',  'price' => 650, 'sort_order' => 2],
                    ],
                ],
                [
                    'name'           => 'Hosepipe',
                    'selection_type' => 'single',
                    'sort_order'     => 2,
                    'items'          => [
                        ['name' => '1.5 m Hosepipe', 'description' => 'Standard household length',    'price' => 200, 'sort_order' => 1],
                        ['name' => '2 m Hosepipe',   'description' => 'Extra reach for tight corners','price' => 270, 'sort_order' => 2],
                        ['name' => '3 m Hosepipe',   'description' => 'For larger kitchen layouts',   'price' => 350, 'sort_order' => 3],
                    ],
                ],
                [
                    'name'           => 'Extras',
                    'selection_type' => 'multi',
                    'sort_order'     => 3,
                    'items'          => [
                        ['name' => 'Piezo Lighter',    'description' => 'Long-neck igniter for gas burners', 'price' => 120, 'sort_order' => 1],
                        ['name' => 'Hose Clamp (x2)', 'description' => 'Stainless steel pipe clamps',       'price' => 80,  'sort_order' => 2],
                        ['name' => 'Burner Guard',    'description' => 'Protective ring for outdoor use',   'price' => 150, 'sort_order' => 3],
                    ],
                ],
            ],

            // ── 25 kg commercial (size_id 4) ─────────────────────────────────
            4 => [
                [
                    'name'           => 'Regulator',
                    'selection_type' => 'single',
                    'sort_order'     => 1,
                    'items'          => [
                        ['name' => 'Industrial Regulator', 'description' => 'High-flow regulator for commercial use', 'price' => 900,  'sort_order' => 1],
                        ['name' => 'Standard Regulator',   'description' => 'Fits standard 25 kg cylinders',         'price' => 550,  'sort_order' => 2],
                    ],
                ],
                [
                    'name'           => 'Hosepipe',
                    'selection_type' => 'single',
                    'sort_order'     => 2,
                    'items'          => [
                        ['name' => '2 m Hosepipe', 'description' => 'Heavy-duty braided hose', 'price' => 350, 'sort_order' => 1],
                        ['name' => '3 m Hosepipe', 'description' => 'Extended reach for larger kitchens', 'price' => 450, 'sort_order' => 2],
                    ],
                ],
            ],

            // ── 50 kg commercial (size_id 5) ─────────────────────────────────
            5 => [
                [
                    'name'           => 'Regulator',
                    'selection_type' => 'single',
                    'sort_order'     => 1,
                    'items'          => [
                        ['name' => 'Industrial Regulator', 'description' => 'High-flow regulator for commercial use', 'price' => 1200, 'sort_order' => 1],
                    ],
                ],
                [
                    'name'           => 'Hosepipe',
                    'selection_type' => 'single',
                    'sort_order'     => 2,
                    'items'          => [
                        ['name' => '3 m Hosepipe', 'description' => 'Heavy-duty braided hose, extended reach', 'price' => 500, 'sort_order' => 1],
                        ['name' => '5 m Hosepipe', 'description' => 'For wide commercial kitchen layouts',    'price' => 700, 'sort_order' => 2],
                    ],
                ],
            ],
        ];

        foreach ($definitions as $sizeId => $groups) {
            foreach ($groups as $sortOrder => $groupDef) {
                $group = AddonGroup::firstOrCreate(
                    ['size_id' => $sizeId, 'name' => $groupDef['name']],
                    [
                        'selection_type' => $groupDef['selection_type'],
                        'sort_order'     => $groupDef['sort_order'],
                        'is_active'      => true,
                    ],
                );

                foreach ($groupDef['items'] as $itemDef) {
                    AddonItem::firstOrCreate(
                        ['group_id' => $group->id, 'name' => $itemDef['name']],
                        [
                            'description' => $itemDef['description'],
                            'price'       => $itemDef['price'],
                            'sort_order'  => $itemDef['sort_order'],
                            'is_active'   => true,
                        ],
                    );
                }
            }
        }
    }
}
