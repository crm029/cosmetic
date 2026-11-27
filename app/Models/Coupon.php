<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Coupon extends Model {
 protected $fillable=['code','type','value','minimum_order_toman','maximum_discount_toman','usage_limit','used_count','starts_at','expires_at','is_active'];
 protected function casts(): array {return ['value'=>'integer','minimum_order_toman'=>'integer','maximum_discount_toman'=>'integer','usage_limit'=>'integer','used_count'=>'integer','starts_at'=>'datetime','expires_at'=>'datetime','is_active'=>'boolean'];}
 public function orders(): HasMany {return $this->hasMany(Order::class);}
 public function isAvailable(): bool {
  $now=now();
  return $this->is_active&&(!$this->starts_at||$this->starts_at->lte($now))&&(!$this->expires_at||$this->expires_at->gte($now))&&($this->usage_limit===null||$this->used_count<$this->usage_limit);
 }
 public function discountFor(int $subtotal): int {
  if($subtotal<$this->minimum_order_toman)return 0;
  $discount=$this->type==='percent'?(int)floor($subtotal*$this->value/100):$this->value;
  if($this->maximum_discount_toman!==null)$discount=min($discount,$this->maximum_discount_toman);
  return min($subtotal,max(0,$discount));
 }
}