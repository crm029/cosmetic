<?php
namespace App\Services;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
class OrderFulfillmentService {
 private function changeStatus(Order $order, string $status, string $note): void
 {
  $previous = $order->status;
  if ($previous === $status) return;
  $order->update(['status' => $status]);
  OrderStatusHistory::create([
   'order_id' => $order->id,
   'from_status' => $previous,
   'to_status' => $status,
   'actor_type' => 'system',
   'note' => $note,
  ]);
 }

 public function deductStock(Order $order): bool {
  return DB::transaction(function()use($order){
   $lockedOrder=Order::whereKey($order->id)->lockForUpdate()->first();
   if($lockedOrder->stock_deducted_at)return true;
   if($lockedOrder->payment_status!=='paid'||$lockedOrder->status==='refund_required')return false;
   if($lockedOrder->status==='cancelled'){$this->changeStatus($lockedOrder, 'refund_required', 'پرداخت پس از لغو سفارش تأیید شد؛ بازپرداخت لازم است.');return false;}
   $requiredByProduct=$lockedOrder->items()->get()->groupBy('product_id')->map(fn($items)=>(int)$items->sum('quantity'));
   if($requiredByProduct->isEmpty()){
    $this->changeStatus($lockedOrder, 'paid_stock_issue', 'کسر موجودی سفارش با مشکل مواجه شد و نیاز به بررسی دارد.');
    return false;
   }
   $products=Product::whereIn('id',$requiredByProduct->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
   $reservations=DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->get();
   if($reservations->isNotEmpty()){
    $activeRows=DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->lockForUpdate()->get();
    $active=$activeRows->groupBy('product_id')->map(fn($rows)=>(int)$rows->sum('quantity'));
    $expected=$requiredByProduct->map(fn($quantity)=>(int)$quantity)->sortKeys()->all();
    $actual=$active->map(fn($quantity)=>(int)$quantity)->sortKeys()->all();
    if($actual!==$expected){
     DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->whereNull('released_at')->whereNull('consumed_at')->update(['released_at'=>now(),'updated_at'=>now()]);
     $this->changeStatus($lockedOrder, 'paid_stock_issue', 'رزروهای فعال سفارش با اقلام سفارش مطابقت ندارند.');
     return false;
    }
   }
   $reservedElsewhere=[];
   if($reservations->isEmpty()){
    $reservedElsewhere=DB::table('stock_reservations')
     ->whereIn('product_id',$requiredByProduct->keys())
     ->where('order_id','!=',$lockedOrder->id)
     ->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())
     ->select('product_id',DB::raw('SUM(quantity) as reserved_quantity'))
     ->groupBy('product_id')->pluck('reserved_quantity','product_id')->all();
   }
   foreach($requiredByProduct as $productId=>$quantity){
    $product=$products->get($productId);
    $freeStock=$product?(int)$product->stock-(int)($reservedElsewhere[$productId]??0):0;
    if(!$product||$freeStock<$quantity){$this->changeStatus($lockedOrder, 'paid_stock_issue', 'موجودی آزاد یکی از اقلام سفارش کافی نیست.');return false;}
   }
   foreach($requiredByProduct as $productId=>$quantity){
    $product=$products->get($productId);$previous=(int)$product->stock;
    $product->stock=$previous-$quantity;$product->save();
    StockMovement::create(['product_id'=>$product->id,'order_id'=>$lockedOrder->id,'type'=>'sale','quantity'=>-$quantity,'previous_stock'=>$previous,'new_stock'=>$product->stock,'note'=>'کسر موجودی بابت سفارش '.$lockedOrder->order_number]);
   }
   if($reservations->isNotEmpty()){
    DB::table('stock_reservations')->where('order_id',$lockedOrder->id)->whereNull('released_at')->whereNull('consumed_at')->update(['consumed_at'=>now(),'updated_at'=>now()]);
   }
   $lockedOrder->update(['stock_deducted_at'=>now()]);\n   $this->changeStatus($lockedOrder, 'processing', 'پرداخت و کسر موجودی سفارش با موفقیت تأیید شد.');
   return true;
  });
 }
}
