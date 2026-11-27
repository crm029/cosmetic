<?php
namespace App\Providers;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  if($this->app->environment('production')) URL::forceScheme('https');
  View::composer('layouts.app',function($view){
   $data=$view->getData();$product=$data['product']??null;$category=$data['category']??null;$storeSettings=StoreSetting::current();
   $productImage=data_get($product,'image');$categoryImage=data_get($category,'image');
   $socialImage=$productImage?asset('storage/'.$productImage):($categoryImage?asset('storage/'.$categoryImage):($storeSettings->logo_path?asset('storage/'.$storeSettings->logo_path):null));
   $catalogFiltered=request()->filled('q')||request()->filled('category')||request()->filled('min_price')||request()->filled('max_price')||request()->boolean('in_stock')||(request()->filled('sort')&&request('sort')!=='latest');
   $noIndex=request()->routeIs('cart.*','checkout.*','admin.*','orders.track*')||(request()->routeIs('shop')&&$catalogFiltered);
   $canonicalUrl=request()->url();
   if($product && filled($product->canonical_url)){
    $candidate=$product->canonical_url;
    $parts=parse_url($candidate);
    if(is_array($parts)&&in_array(strtolower($parts['scheme']??''),['http','https'],true)&&!empty($parts['host'])&&strcasecmp($parts['host'],request()->getHost())===0){
     $canonicalUrl=$candidate;
    }
   } elseif(request()->routeIs('shop','categories.show')&&request()->integer('page',1)>1){
    $canonicalUrl=request()->fullUrlWithQuery(['page'=>request()->integer('page')]);
   }
   $view->with(['storeSettings'=>$storeSettings,'socialImage'=>$socialImage,'noIndex'=>$noIndex,'canonicalUrl'=>$canonicalUrl]);
  });
 }
}