<?php
namespace App\Http\Controllers;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class ContactController extends Controller {
 public function index(): View { return view('contact.index',['seoTitle'=>'ارتباط با ما | '.config('app.name'),'seoDescription'=>'برای پرسش، پیشنهاد یا پیگیری با فروشگاه در ارتباط باشید.']); }
 public function store(Request $request): RedirectResponse {
  $data=$request->validate([
   'name'=>['required','string','max:120'],
   'mobile'=>['nullable','regex:/^09[0-9]{9}$/'],
   'email'=>['nullable','email','max:190'],
   'subject'=>['nullable','string','max:150'],
   'message'=>['required','string','min:10','max:5000'],
   'website'=>['prohibited'],
  ]);
  unset($data['website']);ContactMessage::create($data);
  return redirect()->route('contact.index')->with('success','پیام شما ثبت شد. در اولین فرصت بررسی می‌کنیم.');
 }
}