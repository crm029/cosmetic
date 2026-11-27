<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class SecurityHeaders {
 public function handle(Request $request,Closure $next): Response {
  $nonce=base64_encode(random_bytes(16));
  $request->attributes->set('csp_nonce',$nonce);
  $response=$next($request);
  $response->headers->set('X-Content-Type-Options','nosniff');
  $response->headers->set('X-Permitted-Cross-Domain-Policies','none');
  $response->headers->set('X-DNS-Prefetch-Control','off');
  $response->headers->set('Origin-Agent-Cluster','?1');
  $response->headers->set('X-Frame-Options','DENY');
  $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
  $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');
  $response->headers->set('Cross-Origin-Opener-Policy','same-origin');
  $policy="default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob: https:; connect-src 'self' https:; media-src 'self' blob:; frame-src 'none'; manifest-src 'self'";
  if(app()->environment('production'))$policy.='; upgrade-insecure-requests';
  $response->headers->set('Content-Security-Policy',$policy);
  if($request->is('admin','admin/*','admin/login','checkout','cart','coupon','account','account/*','order/track','order/*/pay','payment/callback')){
   $response->headers->set('Cache-Control','no-store, private, max-age=0');
   $response->headers->set('Pragma','no-cache');
   $response->headers->set('Expires','0');
  }
  if($request->isSecure())$response->headers->set('Strict-Transport-Security','max-age=31536000; includeSubDomains');
  return $response;
 }
}