<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Product extends Model {
 protected $fillable=['category_id','name','slug','sku','short_description','description','price','compare_price','stock','image','gallery','model_3d','is_active','is_featured','seo_title','seo_description','canonical_url'];
 protected function casts(): array { return ['price'=>'integer','compare_price'=>'integer','stock'=>'integer','gallery'=>'array','is_active'=>'boolean','is_featured'=>'boolean']; }
 public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}