<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
class AdminAuthController extends Controller {
 public function show(): View { return view('admin.login'); }
 public function login(Request $request) {
  $data=$request->validate(['mobile'=>['required','string'],'password'=>['required','string']]);
  if(!Auth::attempt(['mobile'=>$data['mobile'],'password'=>$data['password'],'is_admin'=>true],$request->boolean('remember'))){
   return back()->withErrors(['mobile'=>'اطلاعات ورود صحیح نیست.'])->onlyInput('mobile');
  }
  $request->session()->regenerate();return redirect()->intended(route('admin.dashboard'));
 }
 public function logout(Request $request) {
  Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
  return redirect()->route('admin.login');
 }
}