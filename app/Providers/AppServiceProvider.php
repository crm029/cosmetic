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
   $data=$view->getData();$product=$data['product']??null;$category=$data['category']??null;
   $socialImage=$product&&$product->image?asset('storage/'.$product->image):($category&&$category->image?asset('storage/'.$category->image):null);
   $noIndex=request()->routeIs('cart.*','checkout.*','admin.*','orders.track*')||(request()->routeIs('shop')&&request()->hasAny(['q','category','min_price','max_price','sort','in_stock']));
   $view->with(['storeSettings'=>StoreSetting::current(),'socialImage'=>$socialImage,'noIndex'=>$noIndex]);
  });
 }
}