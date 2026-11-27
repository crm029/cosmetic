<?php
namespace Tests;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(User $admin, $guard = null): static
    {
        if (!$admin->totp_enabled_at) {
            $admin->forceFill([
                'totp_secret' => app(TotpService::class)->generateSecret(),
                'totp_enabled_at' => now(),
                'totp_last_used_step' => 0,
            ])->save();
        }
        return $this->actingAs($admin, $guard)->withSession(['admin_mfa_verified' => true]);
    }
}