<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
class ResetAdminMfa extends Command
{
    protected $signature = 'admin:mfa-reset {mobile : The administrator mobile number}';
    protected $description = 'Reset administrator TOTP enrollment after verifying server ownership';

    public function handle(): int
    {
        $mobile = (string) $this->argument('mobile');
        $admin = User::where('mobile', $mobile)->where('is_admin', true)->first();
        if (!$admin) {
            $this->error('Administrator account not found.');
            return self::FAILURE;
        }

        if (!$this->confirm('This removes the current MFA device. Continue only after verifying server ownership?', false)) {
            $this->warn('MFA reset cancelled.');
            return self::SUCCESS;
        }

        $admin->forceFill([
            'totp_secret' => null,
            'totp_enabled_at' => null,
            'totp_last_used_step' => 0,
        ])->save();

        $this->info('MFA was reset. The administrator must enroll a new authenticator on next login.');
        return self::SUCCESS;
    }
}