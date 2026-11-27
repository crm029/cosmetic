<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('store:about',function(){ $this->comment('Cosmetic Store - Laravel storefront'); })->purpose('Display store project information');
Artisan::command('store:make-admin',function(){
 $name=trim((string)$this->ask('نام مدیر'));$mobile=trim((string)$this->ask('شماره موبایل مدیر'));
 if($name===''||!preg_match('/^09[0-9]{9}$/',$mobile)){ $this->error('نام و شماره موبایل معتبر وارد کنید.');return 1; }
 if(User::where('mobile',$mobile)->exists()){ $this->error('این شماره قبلاً ثبت شده است.');return 1; }
 $password=$this->secret('رمز عبور (حداقل 12 کاراکتر)');
 if(strlen((string)$password)<12){$this->error('رمز عبور باید حداقل 12 کاراکتر باشد.');return 1;}
 User::create(['name'=>$name,'mobile'=>$mobile,'password'=>$password,'is_admin'=>true]);
 $this->info('حساب مدیر با موفقیت ساخته شد.');return 0;
})->purpose('Create the first store administrator securely from the command line');

Schedule::call(fn () => app(\App\Services\StockReservationService::class)->releaseExpired())
 ->everyMinute()
 ->name('release-expired-stock-reservations')
 ->withoutOverlapping();

Schedule::call(fn () => \\App\\Models\\PhoneVerification::where('expires_at', '<', now()->subDay())->delete())
 ->daily()
 ->name('prune-expired-phone-verifications')
 ->withoutOverlapping();
