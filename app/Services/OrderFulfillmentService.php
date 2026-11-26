<?php
namespace App\Services;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
class OrderFulfillmentService {
 public function deductStock(Order $order): bool {
  return DB::transaction(function()use($order){
   $locked=Order::whereKey($order->id)->lockForUpdate()->first();
   if($locked->stock_deducted_at)return true;
   if($locked->payment_status!=='paid')return false;
   if($locked->status==='cancelled'){$locked->update(['status'=>'paid_stock_issue']);return false;}
   foreach($locked->items as $item){
    $product=Product::whereKey($item->product_id)->lockForUpdate()->first();
    if(!$product||$product->stock<$item->quantity){$locked->update(['status'=>'paid_stock_issue']);return false;}
   }
   foreach($locked->items as $item)Product::whereKey($item->product_id)->decrement('stock',$item->quantity);
   $locked->update(['stock_deducted_at'=>now(),'status'=>'processing']);
   return true;
  });
 }
}