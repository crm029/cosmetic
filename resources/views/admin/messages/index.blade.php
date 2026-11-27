@extends('layouts.app')
@section('content')
<section class="admin-wrap">
 <div class="admin-top"><div><span class="eyebrow">CUSTOMER CARE</span><h1>پیام‌های مشتریان</h1><p class="admin-muted">از این بخش پیام‌ها را جست‌وجو و پیگیری کن.</p></div><a class="text-link" href="{{ route('admin.dashboard') }}">بازگشت به مدیریت ←</a></div>
 @if(session('success'))<p class="notice-success">{{ session('success') }}</p>@endif
 <div class="message-stats"><div><span>کل پیام‌ها</span><strong>{{ number_format($totalCount) }}</strong></div><div><span>خوانده‌نشده</span><strong>{{ number_format($unreadCount) }}</strong></div></div>
 <div class="admin-panel">
  <form method="GET" action="{{ route('admin.messages') }}" class="message-filter-form">
   <label>جست‌وجو
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="نام، موبایل، ایمیل، موضوع یا متن پیام">
   </label>
   <label>وضعیت پیام
    <select name="status">
     <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>همه پیام‌ها</option>
     <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>خوانده‌نشده</option>
     <option value="read" @selected(($filters['status'] ?? '') === 'read')>خوانده‌شده</option>
    </select>
   </label>
   <button class="button button-dark" type="submit">اعمال فیلتر</button>
   <a class="text-link" href="{{ route('admin.messages') }}">پاک‌کردن فیلترها</a>
  </form>
  <div class="admin-table">
   <div class="admin-row message-admin-head"><span>فرستنده</span><span>راه ارتباطی</span><span>موضوع و پیام</span><span>وضعیت</span><span>عملیات</span></div>
   @forelse($messages as $message)
    <div class="admin-row message-admin-row">
     <span>{{ $message->name }}<small>{{ $message->created_at->format('Y/m/d H:i') }}</small></span>
     <span>{{ $message->mobile ?: '—' }}<small>{{ $message->email ?: '—' }}</small></span>
     <span><strong>{{ $message->subject ?: 'بدون موضوع' }}</strong><p>{{ $message->message }}</p></span>
     <span><span class="message-state {{ $message->is_read ? 'is-read' : 'is-unread' }}">{{ $message->is_read ? 'خوانده‌شده' : 'جدید' }}</span></span>
     <span><form method="POST" action="{{ route('admin.messages.read',$message) }}">@csrf @method('PATCH')<input type="hidden" name="is_read" value="{{ $message->is_read ? 0 : 1 }}"><button class="text-link" type="submit">{{ $message->is_read ? 'بازگردانی به خوانده‌نشده' : 'علامت‌گذاری به‌عنوان خوانده‌شده' }}</button></form></span>
    </div>
   @empty
    <p class="message-empty">پیامی با این مشخصات پیدا نشد.</p>
   @endforelse
  </div>
  <div class="pagination-wrap">{{ $messages->links() }}</div>
 </div>
</section>
@endsection
