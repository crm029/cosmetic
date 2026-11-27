<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureCustomer
{
 public function handle(Request $request, Closure $next): Response
 {
  if (!$request->user() || $request->user()->is_admin) return redirect()->route('customer.login');
  return $next($request);
 }
}