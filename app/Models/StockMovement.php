<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StockMovement extends Model {
 protected $fillable=['product_id','order_id','admin_user_id','type','quantity','previous_stock','new_stock','note'];
 protected function casts(): array { return ['quantity'=>'integer','previous_stock'=>'integer','new_stock'=>'integer']; }
 public function product(): BelongsTo { return $this->belongsTo(Product::class); }
 public function order(): BelongsTo { return $this->belongsTo(Order::class); }
 public function admin(): BelongsTo { return $this->belongsTo(User::class,'admin_user_id'); }
}