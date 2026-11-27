<?php
namespace App\Http\Controllers;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
class AdminMfaController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->is_admin, 403);
    }

    public function setup(Request $request, TotpService $totp): View|RedirectResponse
    {
        $this->ensureAdmin($request);
        if ($request->user()->totp_enabled_at) return redirect()->route('admin.mfa.verify');
        $secret = $request->session()->get('admin_totp_setup_secret');
        if (!$secret) {
            $secret = $totp->generateSecret();
            $request->session()->put('admin_totp_setup_secret', $secret);
        }
        return view('admin.mfa-setup', compact('secret'));
    }

    public function enable(Request $request, TotpService $totp): RedirectResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('admin_totp_setup_secret');
        abort_unless(is_string($secret) && $secret !== '', 419);
        $step = $totp->verify($secret, $data['code'], now()->timestamp);
        if ($step === null) return back()->withErrors(['code' => 'کد تأیید معتبر نیست. ساعت تلفن همراه را بررسی کنید.']);
        $user = $request->user();
        $user->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => now(), 'totp_last_used_step' => $step])->save();
        $request->session()->forget('admin_totp_setup_secret');
        $request->session()->put('admin_mfa_verified', true);
        return redirect()->route('admin.dashboard')->with('success', 'تأیید دومرحله‌ای فعال شد.');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $this->ensureAdmin($request);
        if (!$request->user()->totp_enabled_at) return redirect()->route('admin.mfa.setup');
        if ($request->session()->boolean('admin_mfa_verified')) return redirect()->route('admin.dashboard');
        return view('admin.mfa-verify');
    }

    public function verifyCode(Request $request, TotpService $totp): RedirectResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        abort_unless($user->totp_enabled_at && $user->totp_secret, 403);
        $step = $totp->verify($user->totp_secret, $data['code'], now()->timestamp);
        if ($step === null || $step <= (int) $user->totp_last_used_step) {
            return back()->withErrors(['code' => 'کد نامعتبر یا قبلاً استفاده‌شده است.']);
        }
        $updated = DB::table('users')->where('id', $user->id)->where('totp_last_used_step', $user->totp_last_used_step)->update(['totp_last_used_step' => $step, 'updated_at' => now()]);
        if (!$updated) return back()->withErrors(['code' => 'این کد قبلاً استفاده شده است؛ کد جدید را وارد کنید.']);
        $request->session()->put('admin_mfa_verified', true);
        $request->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }
}