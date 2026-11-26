<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Order extends Model {
 protected $fillable=['user_id','order_number','customer_name','mobile','email','shipping_address','subtotal','shipping_amount','total','status','payment_status','payment_authority','payment_ref_id','refund_ref_id','refund_note','refunded_at','stock_deducted_at','sms_sent_at','sms_claimed_at','shipment_sms_claimed_at','shipping_carrier','tracking_code','shipped_at','delivered_at','shipment_sms_sent_at','notes'];
 protected function casts(): array { return ['subtotal'=>'integer','shipping_amount'=>'integer','total'=>'integer','refunded_at'=>'datetime','stock_deducted_at'=>'datetime','sms_sent_at'=>'datetime','shipped_at'=>'datetime','delivered_at'=>'datetime','shipment_sms_sent_at'=>'datetime','sms_claimed_at'=>'datetime','shipment_sms_claimed_at'=>'datetime']; }
 public function items(): HasMany { return $this->hasMany(OrderItem::class); }
 public function user(): BelongsTo { return $this->belongsTo(User::class); }
}