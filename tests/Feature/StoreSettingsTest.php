<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر تنظیمات',
            'mobile' => '09123456791',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
    }

    private function settingsPayload(): array
    {
        return [
            'store_name' => 'فروشگاه تست',
            'tagline' => 'شعار تست',
            'shipping_fee_toman' => 30000,
            'free_shipping_threshold_toman' => 500000,
            'support_mobile' => '09123456789',
            'support_email' => 'support@example.test',
            'address' => 'تهران',
            'ink_color' => '#29251f',
            'accent_color' => '#a77d6a',
        ];
    }

    public function test_store_logo_replacement_deletes_old_file_after_saving_new_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $settings = StoreSetting::current();
        $oldLogo = 'store/old-logo.png';
        Storage::disk('public')->put($oldLogo, 'old-logo');
        $settings->update(['logo_path' => $oldLogo]);

        $this->actingAsAdmin($admin)->put(route('admin.settings.update'), array_merge(
            $this->settingsPayload(),
            ['logo' => UploadedFile::fake()->image('new-logo.png')]
        ))->assertRedirect()->assertSessionHasNoErrors();

        $updated = $settings->fresh();
        $this->assertNotSame($oldLogo, $updated->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertExists($updated->logo_path);
    }

    public function test_store_logo_can_be_removed_without_uploading_a_replacement(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $settings = StoreSetting::current();
        $logo = 'store/remove-logo.png';
        Storage::disk('public')->put($logo, 'logo');
        $settings->update(['logo_path' => $logo]);

        $this->actingAsAdmin($admin)->put(route('admin.settings.update'), array_merge(
            $this->settingsPayload(),
            ['remove_logo' => 1]
        ))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($settings->fresh()->logo_path);
        Storage::disk('public')->assertMissing($logo);
    }
}
