<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
class AdminAuthController extends Controller {
 public function show(): View { return view('admin.login'); }
 public function login(Request $request) {
  $data=$request->validate(['mobile'=>['required','regex:/^09[0-9]{9}$/'],'password'=>['required','string','max:200'],'remember'=>['sometimes','boolean']]);
  if(!Auth::attempt(['mobile'=>$data['mobile'],'password'=>$data['password'],'is_admin'=>true],$request->boolean('remember'))){
   return back()->withErrors(['mobile'=>'اطلاعات ورود صحیح نیست.'])->onlyInput('mobile');
  }
  $request->session()->regenerate();$request->session()->forget('admin_mfa_verified');
  return redirect()->route(Auth::user()->totp_enabled_at?'admin.mfa.verify':'admin.mfa.setup');
 }
 public function logout(Request $request) {
  abort_unless(Auth::user() && Auth::user()->is_admin, 403);
  Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
  return redirect()->route('admin.login');
 }
}