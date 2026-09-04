<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns an order from a cylinder into a container of them.
 *
 * Additive on purpose. Every column this table replaces stays exactly where
 * it is on `orders`, still written and still read, so nothing downstream
 * changes and the app already in customers' hands keeps working. Readers move
 * across one at a time in a later phase; the legacy columns are dropped, if
 * ever, only once none of them is left.
 *
 * order_type lives here rather than on the order, so one basket can hold a
 * swap and a new cylinder at once. `orders.order_type` keeps its column and
 * narrows its job: it separates gas from accessory, and for a gas order it
 * mirrors the first item until phase three.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->constrained('cylinder_sizes');
            $table->foreignId('brand_id')->nullable()
                ->constrained('gas_brands')->nullOnDelete();
            $table->enum('order_type', ['swap', 'new_cylinder']);
            $table->unsignedInteger('quantity')->default(1);

            // Snapshots, per unit — the same reason order_addons stores a
            // price rather than joining to the catalogue. A price change next
            // month must not rewrite what somebody paid last month.
            $table->unsignedInteger('gas_price');
            $table->unsignedInteger('cylinder_price')->default(0);
            $table->unsignedInteger('line_total');

            $table->timestamps();

            $table->index('order_id', 'idx_order_items_order');

            // Makes the cart's merge rule a database guarantee rather than a
            // UI convention: adding EldoGas 13kg twice can only raise a
            // quantity, while Total 13kg is a different row because the key
            // differs. MySQL permits repeated NULLs here, so two no-brand
            // lines of the same size could still coexist — worth knowing, not
            // worth a sentinel value to prevent.
            $table->unique(
                ['order_id', 'size_id', 'brand_id', 'order_type'],
                'uniq_order_item_config',
            );
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }

    /**
     * One item per existing order, from that order's own columns.
     *
     * Accessory orders are skipped: they carry no cylinder, so they have no
     * item to write. Their contents live in order_addons and always did.
     */
    private function backfill(): void
    {
        DB::table('orders')
            ->whereNotNull('size_id')
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                $rows = [];

                foreach ($orders as $order) {
                    $gas = (int) $order->gas_price;
                    $cylinder = (int) $order->cylinder_price;

                    $rows[] = [
                        'order_id' => $order->id,
                        'size_id' => $order->size_id,
                        'brand_id' => $order->brand_id,
                        'order_type' => $order->order_type,
                        'quantity' => 1,
                        'gas_price' => $gas,
                        'cylinder_price' => $cylinder,
                        // Deliberately not the order total: delivery, addons
                        // and any points discount belong to the order, not to
                        // the cylinder on it.
                        'line_total' => $gas + $cylinder,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];
                }

                DB::table('order_items')->insert($rows);
            });
    }
};
