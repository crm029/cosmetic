<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    public function deductStock(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder->stock_deducted_at) {
                return true;
            }

            if ($lockedOrder->payment_status !== 'paid') {
                return false;
            }

            if ($lockedOrder->status === 'cancelled') {
                $lockedOrder->update(['status' => 'paid_stock_issue']);
                return false;
            }

            // Aggregate repeated order lines, then lock products in a stable order.
            // This prevents duplicate-line over-selling and reduces deadlock risk.
            $requiredByProduct = $lockedOrder->items()
                ->get()
                ->groupBy('product_id')
                ->map(fn ($items) => (int) $items->sum('quantity'));

            $products = Product::whereIn('id', $requiredByProduct->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requiredByProduct as $productId => $quantity) {
                $product = $products->get($productId);

                if (!$product || $product->stock < $quantity) {
                    $lockedOrder->update(['status' => 'paid_stock_issue']);
                    return false;
                }
            }

            foreach ($requiredByProduct as $productId => $quantity) {
                $product = $products->get($productId);
                $previous = (int) $product->stock;
                $product->stock = $previous - $quantity;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'order_id' => $lockedOrder->id,
                    'type' => 'sale',
                    'quantity' => -$quantity,
                    'previous_stock' => $previous,
                    'new_stock' => $product->stock,
                    'note' => 'کسر موجودی بابت سفارش '.$lockedOrder->order_number,
                ]);
            }

            $lockedOrder->update([
                'stock_deducted_at' => now(),
                'status' => 'processing',
            ]);

            return true;
        });
    }
}
