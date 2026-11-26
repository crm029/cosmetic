<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StoreSetting extends Model {
 protected $fillable=['store_name','tagline','shipping_fee_toman','free_shipping_threshold_toman','support_mobile','support_email','address','ink_color','accent_color'];
 protected function casts(): array { return ['shipping_fee_toman'=>'integer','free_shipping_threshold_toman'=>'integer']; }
 public static function current(): self {
  return static::query()->firstOrCreate(['id'=>1],['store_name'=>config('app.name','Cosmetic Store'),'shipping_fee_toman'=>0,'free_shipping_threshold_toman'=>0,'ink_color'=>'#29251f','accent_color'=>'#a77d6a']);
 }
}