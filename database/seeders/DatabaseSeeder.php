<?php
namespace Database\\Seeders;
use App\\Models\\Category;
use Illuminate\\Database\\Seeder;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  foreach(['مراقبت پوست'=>'skin-care','مراقبت مو'=>'hair-care','آرایشی'=>'makeup','بهداشت و بدن'=>'body-care'] as $name=>$slug)
   Category::firstOrCreate(['slug'=>$slug],['name'=>$name,'is_active'=>true]);
 }
}