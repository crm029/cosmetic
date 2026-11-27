<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureAdmin {
 public function handle(Request $request,Closure $next): Response {
  if(!$request->user()||!$request->user()->is_admin)return redirect()->route('admin.login');
  if(!$request->user()->totp_enabled_at)return redirect()->route('admin.mfa.setup');
  if(!(bool) $request->session()->get('admin_mfa_verified'))return redirect()->route('admin.mfa.verify');
  return $next($request);
 }
}