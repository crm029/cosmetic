<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
class CustomerAuthController extends Controller {
 public function show(): View{return view('account.login');}
 public function sendCode(Request $request) {
  $data=$request->validate(['mobile'=>['required','regex:/^09[0-9]{9}$/']]);$mobile=$data['mobile'];
  if(User::where('mobile',$mobile)->where('is_admin',true)->exists())return back()->withErrors(['mobile'=>'امکان ورود مشتری با این شماره وجود ندارد.'])->withInput();
  $last=PhoneVerification::where('mobile',$mobile)->latest()->first();
  if($last&&$last->created_at->gt(now()->subSeconds(60)))return back()->withErrors(['mobile'=>'برای دریافت کد جدید کمی صبر کنید.'])->withInput();
  $code=(string)random_int(100000,999999);
  $base=config('services.0098sms.base_url');$username=config('services.0098sms.username');$password=config('services.0098sms.password');$sender=config('services.0098sms.sender');$domain=config('services.0098sms.domain','0098');
  if(!$base||!$username||!$password||!$sender||!$domain||!str_starts_with($base,'https://'))return back()->withErrors(['mobile'=>'ارسال پیامک در حال حاضر در دسترس نیست.'])->withInput();
  try {
   $response=Http::timeout(15)->get($base,['FROM'=>$sender,'TO'=>$mobile,'TEXT'=>'کد ورود شما: '.$code.' — اعتبار: ۵ دقیقه','USERNAME'=>$username,'PASSWORD'=>$password,'DOMAIN'=>$domain]);
   if(!$response->successful()||trim($response->body())!=='0')return back()->withErrors(['mobile'=>'ارسال کد تأیید ناموفق بود. دوباره تلاش کنید.'])->withInput();
  }catch(\Throwable $e){Log::warning('Customer OTP delivery failed',['exception'=>$e::class]);return back()->withErrors(['mobile'=>'ارتباط با سرویس پیامک برقرار نشد.'])->withInput();}
  PhoneVerification::where('mobile',$mobile)->whereNull('consumed_at')->delete();
  PhoneVerification::create(['mobile'=>$mobile,'code_hash'=>Hash::make($code),'expires_at'=>now()->addMinutes(5)]);
  $request->session()->put('customer_otp_mobile',$mobile);
  return back()->with('otp_sent','کد تأیید پنج‌رقمی/شش‌رقمی برای شما ارسال شد.');
 }
 public function verify(Request $request) {
  $data=$request->validate(['mobile'=>['required','regex:/^09[0-9]{9}$/'],'code'=>['required','digits:6'],'name'=>['nullable','string','max:120']]);
  if($request->session()->get('customer_otp_mobile')!==$data['mobile'])return back()->withErrors(['mobile'=>'ابتدا برای همین شماره درخواست کد تأیید کنید.']);
  $verification=PhoneVerification::where('mobile',$data['mobile'])->whereNull('consumed_at')->latest()->first();
  if(!$verification||$verification->expires_at->isPast()||$verification->attempts>=5||!Hash::check($data['code'],$verification->code_hash)){
   if($verification&&!$verification->consumed_at)$verification->increment('attempts');
   return back()->withErrors(['code'=>'کد تأیید نادرست است یا اعتبار آن پایان یافته است.'])->withInput(['mobile'=>$data['mobile']]);
  }
  if(User::where('mobile',$data['mobile'])->where('is_admin',true)->exists())return back()->withErrors(['mobile'=>'امکان ورود مشتری با این شماره وجود ندارد.']);
  $verification->update(['consumed_at'=>now()]);
  $user=User::where('mobile',$data['mobile'])->first();
  if(!$user){
   $order=Order::where('mobile',$data['mobile'])->latest()->first();
   $user=User::create(['name'=>$data['name']?:($order?->customer_name?:'مشتری'),'mobile'=>$data['mobile'],'password'=>Str::random(48),'is_admin'=>false]);
  }elseif($data['name']&&$user->name==='مشتری')$user->update(['name'=>$data['name']]);
  Order::where('mobile',$data['mobile'])->whereNull('user_id')->update(['user_id'=>$user->id]);
  Auth::login($user);$request->session()->regenerate();$request->session()->forget('customer_otp_mobile');
  return redirect()->intended(route('account.index'))->with('success','ورود شما با موفقیت انجام شد.');
 }
 public function index(): View {
  abort_unless(auth()->check()&&!auth()->user()->is_admin,403);
  $orders=Order::where('user_id',auth()->id())->with('items')->latest()->paginate(10);
  return view('account.index',compact('orders'));
 }
 public function logout(Request $request) {
  abort_unless(auth()->check()&&!auth()->user()->is_admin,403);
  Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('customer.login');
 }
}