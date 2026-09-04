<?php

namespace App\Exceptions;

use Exception;

class OutOfStockException extends Exception
{
    public function __construct(
        string $message = 'This cylinder size is currently out of stock.',
        public readonly ?int $sizeId = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Names the cylinder that stopped the order.
     *
     * An order can hold several now, so "this cylinder size is out of stock"
     * leaves the customer to guess which of four they need to change. It also
     * says how many are left when some are — a shopper who asked for three
     * and can have two would rather know that than be refused flatly.
     */
    public static function forSize(?string $name, int $wanted, int $available): self
    {
        $label = $name ?? 'One of the cylinders you selected';

        if ($available > 0 && $wanted > 1) {
            return new self(
                "{$label} — only {$available} left, and you asked for {$wanted}.",
            );
        }

        return new self("{$label} is currently out of stock.");
    }
}
