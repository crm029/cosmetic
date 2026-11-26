<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
Artisan::command('store:about',function(){ $this->comment('Cosmetic Store - Laravel storefront'); })->purpose('Display store project information');
Artisan::command('store:make-admin',function(){
 $name=$this->ask('نام مدیر');$mobile=$this->ask('شماره موبایل مدیر');
 if(User::where('mobile',$mobile)->exists()){ $this->error('این شماره قبلاً ثبت شده است.');return 1; }
 $password=$this->secret('رمز عبور (حداقل 12 کاراکتر)');
 if(strlen((string)$password)<12){$this->error('رمز عبور باید حداقل 12 کاراکتر باشد.');return 1;}
 User::create(['name'=>$name,'mobile'=>$mobile,'password'=>$password,'is_admin'=>true]);
 $this->info('حساب مدیر با موفقیت ساخته شد.');return 0;
})->purpose('Create the first store administrator securely from the command line');