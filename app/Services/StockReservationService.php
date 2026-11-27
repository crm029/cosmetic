<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockReservationService
{
    public function reserveForOrder(Order $order): void
    {
        $items = $order->items()->get()->groupBy('product_id')->map(
            fn ($rows) => (int) $rows->sum('quantity')
        );

        if ($items->isEmpty() || $items->keys()->contains(null)) {
            throw new RuntimeException('اقلام سفارش برای رزرو موجودی معتبر نیستند.');
        }

        $productIds = $items->keys()->map(fn ($id) => (int) $id)->sort()->values();
        $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw new RuntimeException('یکی از محصولات سفارش دیگر در دسترس نیست.');
        }

        $now = now();
        $existing = DB::table('stock_reservations')
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        if ($existing->map(fn ($qty) => (int) $qty)->all() === $items->map(fn ($qty) => (int) $qty)->all()) {
            return;
        }

        DB::table('stock_reservations')
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->update(['released_at' => $now, 'updated_at' => $now]);

        $reservedElsewhere = DB::table('stock_reservations')
            ->whereIn('product_id', $productIds)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->select('product_id', DB::raw('SUM(quantity) as reserved_quantity'))
            ->groupBy('product_id')
            ->pluck('reserved_quantity', 'product_id');

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);
            $reserved = (int) ($reservedElsewhere[$productId] ?? 0);
            if ((int) $product->stock - $reserved < $quantity) {
                throw new RuntimeException('موجودی '.$product->name.' برای این سفارش کافی نیست. لطفاً سبد خرید را اصلاح کنید.');
            }
        }

        $expiresAt = $now->copy()->addMinutes(max(5, (int) config('services.inventory.reservation_minutes', 30)));
        foreach ($items as $productId => $quantity) {
            DB::table('stock_reservations')->insert([
                'order_id' => $order->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function releaseForOrder(Order $order): void
    {
        $now = now();
        DB::table('stock_reservations')
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->update(['released_at' => $now, 'updated_at' => $now]);
    }

    public function releaseExpired(): int
    {
        $now = now();

        return DB::table('stock_reservations')
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '<=', $now)
            ->update(['released_at' => $now, 'updated_at' => $now]);
    }
}
