<?php
namespace App\\Http\\Controllers;
use App\\Models\\Category;
use App\\Models\\Product;
use Illuminate\\View\\View;
class HomeController extends Controller {
 public function index(): View {
  return view('home',[
   'categories'=>Category::where('is_active',true)->orderBy('sort_order')->take(6)->get(),
   'products'=>Product::where('is_active',true)->latest()->take(8)->get(),
   'seoTitle'=>config('app.name').' | زیبایی، با انتخابی آگاهانه',
   'seoDescription'=>'فروشگاه تخصصی محصولات مراقبت پوست، مو و زیبایی با تجربه‌ای مدرن و متفاوت.'
  ]);
 }
}