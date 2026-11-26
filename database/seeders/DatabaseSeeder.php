<?php
namespace Database\Seeders;
use App\Models\Category;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  StoreSetting::firstOrCreate(['id'=>1],['store_name'=>config('app.name','Cosmetic Store'),'shipping_fee_toman'=>0,'free_shipping_threshold_toman'=>0,'ink_color'=>'#29251f','accent_color'=>'#a77d6a']);
  foreach(['مراقبت پوست'=>'skin-care','مراقبت مو'=>'hair-care','آرایشی'=>'makeup','بهداشت و بدن'=>'body-care'] as $name=>$slug)
   Category::firstOrCreate(['slug'=>$slug],['name'=>$name,'is_active'=>true]);
 }
}