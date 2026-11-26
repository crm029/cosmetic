<?php
namespace App\Services;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
class OrderFulfillmentService {
 public function deductStock(Order $order): bool {
  return DB::transaction(function()use($order){
   $locked=Order::whereKey($order->id)->lockForUpdate()->first();
   if($locked->stock_deducted_at)return true;
   if($locked->payment_status!=='paid')return false;
   if($locked->status==='cancelled'){$locked->update(['status'=>'paid_stock_issue']);return false;}
   $stockBefore=[];
   foreach($locked->items as $item){
    $product=Product::whereKey($item->product_id)->lockForUpdate()->first();
    if(!$product||$product->stock<$item->quantity){$locked->update(['status'=>'paid_stock_issue']);return false;}
    $stockBefore[$product->id]=$product->stock;
   }
   foreach($locked->items as $item){
    Product::whereKey($item->product_id)->decrement('stock',$item->quantity);
    $previous=$stockBefore[$item->product_id];
    StockMovement::create(['product_id'=>$item->product_id,'order_id'=>$locked->id,'type'=>'sale','quantity'=>-$item->quantity,'previous_stock'=>$previous,'new_stock'=>$previous-$item->quantity,'note'=>'کسر موجودی بابت سفارش '.$locked->order_number]);
   }
   $locked->update(['stock_deducted_at'=>now(),'status'=>'processing']);
   return true;
  });
 }
}