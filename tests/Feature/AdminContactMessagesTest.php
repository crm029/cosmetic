<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر پیام‌ها',
            'mobile' => '09123456780',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
    }

    private function message(array $overrides = []): ContactMessage
    {
        return ContactMessage::create(array_merge([
            'name' => 'مشتری نمونه',
            'mobile' => '09111111111',
            'email' => 'customer@example.test',
            'subject' => 'پیگیری سفارش',
            'message' => 'لطفاً درباره سفارش من راهنمایی کنید.',
            'is_read' => false,
        ], $overrides));
    }

    public function test_admin_can_search_contact_messages_across_sender_subject_and_message(): void
    {
        $admin = $this->admin();
        $match = $this->message();
        $other = $this->message([
            'name' => 'مشتری دیگر',
            'mobile' => '09222222222',
            'subject' => 'پیشنهاد',
            'message' => 'یک پیشنهاد برای فروشگاه دارم.',
        ]);

        $this->actingAs($admin)->get(route('admin.messages', ['q' => 'پیگیری سفارش']))
            ->assertOk()->assertSee($match->subject)->assertDontSee($other->name);
    }

    public function test_admin_can_filter_unread_and_read_messages(): void
    {
        $admin = $this->admin();
        $unread = $this->message();
        $read = $this->message(['name' => 'مشتری خوانده‌شده', 'is_read' => true]);

        $this->actingAs($admin)->get(route('admin.messages', ['status' => 'unread']))
            ->assertOk()->assertSee($unread->name)->assertDontSee($read->name);

        $this->actingAs($admin)->get(route('admin.messages', ['status' => 'read']))
            ->assertOk()->assertSee($read->name)->assertDontSee($unread->name);
    }

    public function test_admin_can_mark_a_message_read_and_restore_it_to_unread(): void
    {
        $admin = $this->admin();
        $message = $this->message();

        $this->actingAs($admin)->patch(route('admin.messages.read', $message), ['is_read' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($message->fresh()->is_read);

        $this->patch(route('admin.messages.read', $message), ['is_read' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($message->fresh()->is_read);
    }

    public function test_contact_message_inbox_remains_admin_only(): void
    {
        $user = User::create([
            'name' => 'کاربر عادی',
            'mobile' => '09123456789',
            'password' => 'a-secure-test-password',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->get(route('admin.messages'))
            ->assertRedirect(route('admin.login'));
    }
}
