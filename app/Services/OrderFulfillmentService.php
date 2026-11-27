<?php
namespace App\Services;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
class OrderFulfillmentService {
 public function deductStock(Order $order): bool {
  return DB::transaction(function()use($order){
   $lockedOrder=Order::whereKey($order->id)->lockForUpdate()->first();
   if($lockedOrder->stock_deducted_at)return true;
   if($lockedOrder->payment_status!=='paid'||$lockedOrder->status==='refund_required')return false;
   if($lockedOrder->status==='cancelled'){$lockedOrder->update(['status'=>'refund_required']);return false;}
   $requiredByProduct=$lockedOrder->items()->get()->groupBy('product_id')->map(fn($items)=>(int)$items->sum('quantity'));
   $products=Product::whereIn('id',$requiredByProduct->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
   $reservations=DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->lockForUpdate()->get();
   if($reservations->isNotEmpty()){
    $active=$reservations->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->groupBy('product_id')->map(fn($rows)=>(int)$rows->sum('quantity'));
    foreach($requiredByProduct as $productId=>$quantity){
     if((int)($active[$productId]??0)!==$quantity){
      DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->whereNull('released_at')->whereNull('consumed_at')->update(['released_at'=>now(),'updated_at'=>now()]);
      $lockedOrder->update(['status'=>'paid_stock_issue']);
      return false;
     }
    }
   }
   foreach($requiredByProduct as $productId=>$quantity){
    $product=$products->get($productId);
    if(!$product||$product->stock<$quantity){$lockedOrder->update(['status'=>'paid_stock_issue']);return false;}
   }
   foreach($requiredByProduct as $productId=>$quantity){
    $product=$products->get($productId);$previous=(int)$product->stock;
    $product->stock=$previous-$quantity;$product->save();
    StockMovement::create(['product_id'=>$product->id,'order_id'=>$lockedOrder->id,'type'=>'sale','quantity'=>-$quantity,'previous_stock'=>$previous,'new_stock'=>$product->stock,'note'=>'کسر موجودی بابت سفارش '.$lockedOrder->order_number]);
   }
   if($reservations->isNotEmpty()){
    DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->whereNull('released_at')->whereNull('consumed_at')->update(['consumed_at'=>now(),'updated_at'=>now()]);
   }
   $lockedOrder->update(['stock_deducted_at'=>now(),'status'=>'processing']);
   return true;
  });
 }
}
