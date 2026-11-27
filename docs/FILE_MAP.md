# راهنمای نقشه فایل‌های پروژه فروشگاه آرایشی

این فایل برای پیدا کردن سریع محل هر قابلیت است. مسیرها نسبت به ریشه مخزن هستند. برای تغییر ظاهر از بخش «قالب‌ها و ظاهر»، برای منطق از «کنترلرها و سرویس‌ها» و برای ساختار پایگاه داده از «مهاجرت‌ها» شروع کن.

## فایل‌های اصلی ریشه پروژه

| فایل | کاربرد |
|---|---|
| .env.example | نمونه متغیرهای محیطی؛ مقادیر واقعی و رمزها باید فقط در فایل محیطی سرور باشند. |
| .gitignore | جلوگیری از ورود فایل‌های محرمانه، vendor، cache، session و فایل‌های محلی به Git. |
| README.md | معرفی پروژه، نیازمندی‌ها و راه‌اندازی. |
| artisan | ورودی دستورات مدیریتی Laravel؛ فایل اجرایی است، معمولاً ویرایش نمی‌شود. |
| composer.json | وابستگی‌های PHP و تنظیمات Composer. |
| phpunit.xml.dist | تنظیمات اجرای تست‌ها. |

## تنظیمات و زیرساخت Laravel

| مسیر | کاربرد |
|---|---|
| bootstrap/app.php | ثبت مسیرها، middlewareهای سراسری و alias مربوط به مدیر. |
| bootstrap/providers.php | فهرست Service Providerهای برنامه. |
| bootstrap/cache/.gitkeep | نگه‌داشتن پوشه cache در Git؛ فایل اجرایی نیست. |
| config/app.php | نام، زبان، timezone و تنظیمات عمومی برنامه. |
| config/auth.php | guard و provider ورود مدیر و مشتری. |
| config/cache.php | تنظیمات cache و rate limiter. |
| config/database.php | اتصال MySQL/SQLite و تنظیمات پایگاه داده. |
| config/filesystems.php | دیسک‌های ذخیره‌سازی فایل و تصاویر. |
| config/hashing.php | الگوریتم و هزینه هش رمزها و کدهای OTP. |
| config/logging.php | مقصد و سطح ثبت گزارش خطاها. |
| config/services.php | تنظیمات زرین‌پال، پیامک و مدت رزرو موجودی. |
| config/session.php | تنظیمات نشست و کوکی ورود. |
| config/view.php | تنظیمات موتور قالب Blade. |
| routes/web.php | تمام آدرس‌های سایت، حساب مشتری، پرداخت و پنل مدیریت. |
| routes/console.php | محل تعریف فرمان‌های اختصاصی زمان‌بندی‌شده/کنسول. |

## امنیت و middleware

| مسیر | کاربرد |
|---|---|
| app/Http/Middleware/EnsureAdmin.php | جلوگیری از دسترسی مشتری یا مهمان به پنل مدیریت و اجبار تأیید دومرحله‌ای مدیر. |
| app/Http/Middleware/EnsureCustomer.php | محدود کردن عملیات حساس پیگیری سفارش به حساب مشتری تأییدشده با OTP. |
| app/Http/Middleware/SecurityHeaders.php | هدرهای امنیتی، CSP nonce، HSTS در HTTPS و جلوگیری از cache صفحات خصوصی. |
| app/Http/Controllers/Controller.php | کلاس پایه کنترلرها. |

## کنترلرها؛ محل منطق درخواست‌ها

| مسیر | کاربرد |
|---|---|
| app/Http/Controllers/AdminAuthController.php | ورود و خروج مدیر؛ پس از رمز عبور، ورود دومرحله‌ای اجباری است. |
| app/Http/Controllers/AdminMfaController.php | ثبت اولیه کلید TOTP، فعال‌سازی و بررسی کد دومرحله‌ای مدیر. |
| app/Http/Controllers/AdminController.php | داشبورد و عملیات مدیر: کالا، دسته، سفارش، مشتری، گزارش، تنظیمات، پیام‌ها، کوپن و انبار. |
| app/Http/Controllers/CartController.php | افزودن، حذف و تغییر تعداد کالا در سبد خرید. |
| app/Http/Controllers/CheckoutController.php | اعتبارسنجی نهایی سبد، محاسبه مبلغ، ساخت سفارش و آغاز پرداخت. |
| app/Http/Controllers/ContactController.php | فرم تماس و ذخیره پیام مشتری. |
| app/Http/Controllers/CouponController.php | اعمال و حذف کد تخفیف در سبد. |
| app/Http/Controllers/CustomerAuthController.php | ورود مشتری با کد یک‌بارمصرف پیامکی و صفحه حساب. |
| app/Http/Controllers/HomeController.php | داده‌های صفحه اصلی. |
| app/Http/Controllers/OrderTrackingController.php | پیگیری سفارش و تلاش مجدد برای پرداخت. |
| app/Http/Controllers/PaymentController.php | دریافت callback و تأیید سمت سرور پرداخت زرین‌پال. |
| app/Http/Controllers/ProductController.php | فهرست فروشگاه، دسته‌بندی و صفحه جزئیات محصول. |
| app/Http/Controllers/ProductReviewController.php | ثبت نظر خریدار و تأیید/رد نظر در پنل. |

## سرویس‌ها؛ منطق حساس و قابل استفاده مجدد

| مسیر | کاربرد |
|---|---|
| app/Services/OrderFulfillmentService.php | تطبیق سفارش پرداخت‌شده، کسر اتمیک موجودی، ثبت گردش انبار و امتیاز وفاداری. |
| app/Services/SmsService.php | ارسال پیامک پرداخت، ارسال مرسوله و تحویل با جلوگیری از ارسال تکراری. |
| app/Services/StockReservationService.php | رزرو، آزادسازی و پاک‌سازی رزروهای منقضی موجودی. |
| app/Services/TotpService.php | تولید کلید محرمانه و محاسبه/بررسی کدهای یک‌بارمصرف TOTP مدیر. |
| app/Services/ZarinpalService.php | درخواست پرداخت، ساخت لینک پرداخت، verify و inquiry زرین‌پال. |

## مدل‌ها؛ ارتباط برنامه با جدول‌ها

| مسیر | کاربرد |
|---|---|
| app/Models/Category.php | دسته‌بندی محصولات. |
| app/Models/ContactMessage.php | پیام‌های فرم تماس. |
| app/Models/Coupon.php | کد تخفیف و محاسبه مبلغ تخفیف. |
| app/Models/LoyaltyPointTransaction.php | دفتر گردش امتیازهای باشگاه مشتریان. |
| app/Models/Order.php | سفارش، پرداخت، ارسال، وضعیت و روابط سفارش. |
| app/Models/OrderItem.php | ردیف‌های کالا در سفارش. |
| app/Models/OrderStatusHistory.php | تاریخچه تغییر وضعیت سفارش و عامل تغییر. |
| app/Models/PhoneVerification.php | هش کد OTP، زمان انقضا و تعداد تلاش. |
| app/Models/Product.php | اطلاعات، قیمت، موجودی، تصاویر و مدل سه‌بعدی محصول. |
| app/Models/ProductReview.php | نظر و امتیاز مشتری برای محصول. |
| app/Models/StockMovement.php | سوابق ورود، اصلاح و خروج موجودی. |
| app/Models/StoreSetting.php | نام فروشگاه، لوگو، رنگ‌ها، نشانی و هزینه ارسال. |
| app/Models/User.php | حساب مدیر و مشتری، امتیاز وفاداری و کلید رمزنگاری‌شده MFA مدیر. |
| app/Providers/AppServiceProvider.php | تنظیمات سراسری، rate limitهای سفارشی و داده مشترک قالب‌ها. |

## مهاجرت‌های پایگاه داده

فایل‌های این پوشه ساختار جدول‌ها را می‌سازند یا تغییر می‌دهند. پس از تغییر migration اجرا و تست می‌شود؛ فایل migration اجراشده را برای اصلاح اطلاعات زنده دستکاری نکن.

| مسیر | کاربرد |
|---|---|
| database/migrations/2026_11_26_000001_create_categories_table.php | جدول دسته‌بندی‌ها. |
| database/migrations/2026_11_26_000002_create_products_table.php | جدول محصولات. |
| database/migrations/2026_11_26_000003_create_users_table.php | جدول کاربران و توکن‌های بازیابی رمز. |
| database/migrations/2026_11_26_000004_create_orders_table.php | جدول سفارش‌ها و اطلاعات پایه پرداخت. |
| database/migrations/2026_11_26_000005_create_order_items_table.php | اقلام هر سفارش. |
| database/migrations/2026_11_26_000006_add_stock_deducted_at_to_orders.php | ثبت زمان کسر موجودی سفارش. |
| database/migrations/2026_11_26_000007_add_model_3d_to_products.php | مسیر فایل مدل سه‌بعدی محصول. |
| database/migrations/2026_11_26_000008_add_sms_sent_at_to_orders.php | زمان ارسال پیامک پرداخت. |
| database/migrations/2026_11_26_000010_create_contact_messages_table.php | پیام‌های فرم تماس. |
| database/migrations/2026_11_26_000011_create_store_settings_table.php | تنظیمات فروشگاه. |
| database/migrations/2026_11_26_000012_add_shipping_tracking_to_orders.php | روش ارسال، شرکت حمل، کد رهگیری و زمان‌های ارسال/تحویل. |
| database/migrations/2026_11_26_000013_create_stock_movements_table.php | گردش موجودی و سوابق انبار. |
| database/migrations/2026_11_26_000014_add_logo_to_store_settings.php | مسیر لوگوی فروشگاه. |
| database/migrations/2026_11_26_000015_add_shipment_sms_sent_at_to_orders.php | زمان پیامک ارسال مرسوله. |
| database/migrations/2026_11_26_000016_add_sms_claim_timestamps_to_orders.php | قفل موقت برای جلوگیری از ارسال هم‌زمان پیامک. |
| database/migrations/2026_11_26_000018_add_refund_audit_fields_to_orders.php | مرجع، توضیح و زمان ثبت بازپرداخت. |
| database/migrations/2026_11_26_000019_make_payment_authority_unique.php | یکتا کردن شناسه Authority درگاه. |
| database/migrations/2026_11_27_000001_create_stock_reservations_table.php | رزرو موقت موجودی محصولات برای سفارش‌های پرداخت‌نشده. |
| database/migrations/2026_11_27_000002_create_order_status_histories_table.php | تاریخچه وضعیت سفارش. |
| database/migrations/2026_11_27_000003_create_coupons_and_order_adjustments.php | جدول کوپن‌ها و فیلدهای تخفیف سفارش. |
| database/migrations/2026_11_27_000004_create_reviews_and_phone_verifications.php | جدول نظرات محصولات و کدهای تأیید شماره موبایل. |
| database/migrations/2026_11_27_000005_create_loyalty_point_transactions.php | امتیاز فعلی کاربران و دفتر تراکنش امتیاز. |
| database/migrations/2026_11_27_000006_add_delivery_sms_tracking_to_orders.php | زمان و قفل ارسال پیامک تحویل سفارش. |
| database/migrations/2026_11_27_000007_add_totp_fields_to_users.php | فیلدهای فعال‌سازی، کلید رمزنگاری‌شده و جلوگیری از استفاده مجدد کد TOTP مدیر. |
| database/seeders/DatabaseSeeder.php | ساخت تنظیمات پایه فروشگاه و دسته‌بندی‌های اولیه هنگام seed؛ حساب مدیر نمی‌سازد. |

## قالب‌ها؛ ظاهر صفحات سایت

| مسیر | کاربرد |
|---|---|
| resources/views/layouts/app.blade.php | قالب مادر سایت، هدر، منو، فوتر، متادیتا، فونت و بارگذاری JS. |
| resources/views/home.blade.php | صفحه اصلی فروشگاه. |
| resources/views/shop/index.blade.php | فهرست محصولات و فیلترها. |
| resources/views/shop/category.blade.php | صفحه دسته‌بندی و داده ساختاریافته آن. |
| resources/views/shop/show.blade.php | جزئیات محصول، تصاویر، مدل سه‌بعدی، مشخصات و نظرات. |
| resources/views/shop/partials/reviews.blade.php | بخش نمایش و ثبت نظر محصول. |
| resources/views/components/product-price.blade.php | نمایش یکپارچه قیمت و قیمت قبلی محصول. |
| resources/views/cart/index.blade.php | صفحه سبد خرید. |
| resources/views/checkout/index.blade.php | فرم نهایی سفارش و انتخاب روش ارسال. |
| resources/views/orders/track.blade.php | فرم و نتیجه پیگیری سفارش. |
| resources/views/contact/index.blade.php | صفحه تماس با ما. |
| resources/views/account/login.blade.php | ورود مشتری با پیامک. |
| resources/views/account/index.blade.php | حساب مشتری، سفارش‌ها و امتیازها. |
| resources/views/pages/about.blade.php | درباره فروشگاه. |
| resources/views/pages/faq.blade.php | پرسش‌های متداول. |
| resources/views/pages/privacy.blade.php | سیاست حریم خصوصی. |
| resources/views/pages/terms.blade.php | قوانین و مقررات خرید. |
| resources/views/seo/sitemap.blade.php | خروجی XML نقشه سایت. |

## قالب‌های پنل مدیریت

| مسیر | کاربرد |
|---|---|
| resources/views/admin/login.blade.php | صفحه ورود مدیر با رمز عبور. |
| resources/views/admin/mfa-setup.blade.php | نمایش کلید محرمانه برای ثبت در برنامه احراز هویت و فعال‌سازی MFA. |
| resources/views/admin/mfa-verify.blade.php | فرم ورود کد شش‌رقمی TOTP مدیر. |
| resources/views/admin/dashboard.blade.php | داشبورد و خلاصه وضعیت فروشگاه. |
| resources/views/admin/products/index.blade.php | فهرست و جست‌وجوی محصولات. |
| resources/views/admin/products/form.blade.php | ساخت و ویرایش محصول، عکس‌ها و GLB. |
| resources/views/admin/products/stock-history.blade.php | گردش و تاریخچه انبار یک محصول. |
| resources/views/admin/categories/index.blade.php | فهرست دسته‌بندی‌ها. |
| resources/views/admin/categories/form.blade.php | ساخت و ویرایش دسته‌بندی. |
| resources/views/admin/orders/index.blade.php | فهرست و فیلتر سفارش‌ها. |
| resources/views/admin/orders/show.blade.php | جزئیات سفارش، تاریخچه، تغییر وضعیت، ارسال و بازپرداخت. |
| resources/views/admin/customers/index.blade.php | فهرست مشتریان بر اساس سفارش‌ها. |
| resources/views/admin/customers/show.blade.php | جزئیات و سوابق خرید مشتری. |
| resources/views/admin/coupons/index.blade.php | ساخت و مدیریت کدهای تخفیف. |
| resources/views/admin/messages/index.blade.php | جست‌وجو و مدیریت پیام‌های تماس. |
| resources/views/admin/reports/index.blade.php | گزارش فروش، مشتریان و کالاهای پرفروش. |
| resources/views/admin/reviews/index.blade.php | بررسی و تأیید/رد نظرات مشتریان. |
| resources/views/admin/settings.blade.php | تنظیمات نام، لوگو، رنگ، تماس و ارسال فروشگاه. |

## فایل‌های عمومی و ظاهر/سه‌بعدی

| مسیر | کاربرد |
|---|---|
| public/index.php | ورودی HTTP برنامه Laravel؛ نباید منطق فروشگاه در آن نوشته شود. |
| public/.htaccess | بازنویسی آدرس‌ها و هدرهای امنیتی برای Apache. |
| public/assets/css/store.css | تمام استایل‌های سایت و پنل، واکنش‌گرایی و ظاهر. |
| public/assets/js/store.js | رفتار فرانت‌اند، انیمیشن‌ها، سبد و نمایش مدل‌های سه‌بعدی. |
| public/assets/images/hero-scene.svg | تصویر برداری صحنه اصلی/جایگزین صفحه اول. |
| public/storage | لینک نمادین فایل‌های public storage که روی سرور ساخته می‌شود؛ فایل واقعی محصولات در Git قرار نمی‌گیرد. |

## تست‌ها

| مسیر | کاربرد |
|---|---|
| tests/TestCase.php | کلاس پایه تست‌ها و helper ورود مدیر با MFA شبیه‌سازی‌شده برای تست‌های پنل. |
| tests/Feature/HomePageTest.php | تست صفحه اصلی و مسیرهای عمومی پایه. |
| tests/Feature/CatalogManagementTest.php | تست مدیریت محصول، دسته‌بندی و فایل‌ها. |
| tests/Feature/CustomerManagementTest.php | تست فهرست و جزئیات مشتریان. |
| tests/Feature/CustomerAccountAndReviewTest.php | تست OTP، حساب مشتری و ثبت/مدیریت نظر. |
| tests/Feature/PaymentOrderLifecycleTest.php | تست چرخه پرداخت و callback. |
| tests/Feature/OrderFulfillmentIntegrityTest.php | تست صحت کسر موجودی و تکمیل سفارش. |
| tests/Feature/StockReservationTest.php | تست رزرو موجودی و جلوگیری از فروش بیش از موجودی. |
| tests/Feature/OrderStatusHistoryTest.php | تست تاریخچه تغییر وضعیت سفارش. |
| tests/Feature/OrderDeliveryNotificationTest.php | تست پیامک تحویل و جلوگیری از ارسال تکراری. |
| tests/Feature/LoyaltyPointsTest.php | تست محاسبه و ثبت امتیاز وفاداری. |
| tests/Feature/CouponAndDeliveryTest.php | تست کوپن و هزینه/روش ارسال. |
| tests/Feature/AdminContactMessagesTest.php | تست جست‌وجو و وضعیت پیام‌های تماس در پنل. |
| tests/Feature/SalesReportTest.php | تست گزارش‌های فروش. |
| tests/Feature/StoreSettingsTest.php | تست تنظیمات و مدیریت لوگو. |
| tests/Feature/SeoTechnicalTest.php | تست canonical، sitemap/robots، structured data و جلوگیری از XSS در JSON-LD. |
| tests/Feature/SecurityHeadersTest.php | تست هدرهای امنیتی، CSP، HSTS و no-cache. |
| tests/Feature/SecurityHardeningTest.php | تست محدودیت تلاش ورود مدیر و OTP و دسترسی مهمان. |
| tests/Feature/AdminMfaTest.php | تست اجباری بودن MFA مدیر، بردار استاندارد TOTP، جلوگیری از بازاستفاده کد و حریم خصوصی پیگیری سفارش. |

## CI و نگهداری وابستگی‌ها

| مسیر | کاربرد |
|---|---|
| .github/workflows/laravel.yml | بررسی خودکار PHP، migration، Blade، JavaScript، Composer audit و تست‌ها در هر push/PR. |
| .github/dependabot.yml | تنظیم بررسی به‌روزرسانی وابستگی‌ها و GitHub Actions. |

## پوشه‌های داده و runtime

| مسیر | کاربرد |
|---|---|
| storage/app/private/.gitkeep | محل فایل‌های خصوصی؛ نباید مستقیم از وب قابل دانلود باشد. |
| storage/app/public/.gitkeep | محل فایل‌های عمومی آپلودشده مثل عکس و GLB. |
| storage/framework/cache/data/.gitkeep | cache فایل‌محور Laravel. |
| storage/framework/sessions/.gitkeep | نشست‌های فایل‌محور در صورت استفاده از SESSION_DRIVER=file. |
| storage/framework/views/.gitkeep | قالب‌های Blade کامپایل‌شده. |
| storage/logs/.gitkeep | محل logهای برنامه؛ دسترسی عمومی به آن ممنوع است. |

## کدام فایل را برای هر تغییر باز کنم؟

- تغییر رنگ، فونت، فاصله، موبایل و ظاهر: public/assets/css/store.css
- تغییر انیمیشن، حرکت صحنه و مدل سه‌بعدی: public/assets/js/store.js
- تغییر هدر، منو، فوتر، متاتگ و قالب عمومی: resources/views/layouts/app.blade.php
- تغییر صفحه محصول: resources/views/shop/show.blade.php و app/Http/Controllers/ProductController.php
- افزودن فیلد محصول یا تغییر اعتبارسنجی: app/Http/Controllers/AdminController.php و app/Models/Product.php؛ اگر ستون جدید لازم است migration تازه بساز.
- تغییر سفارش یا پرداخت: app/Http/Controllers/CheckoutController.php، app/Http/Controllers/PaymentController.php و سرویس‌های مرتبط.
- تغییر موجودی: app/Services/StockReservationService.php و app/Services/OrderFulfillmentService.php.
- تغییر پیامک: app/Services/SmsService.php و config/services.php.
- تغییر درگاه: app/Services/ZarinpalService.php و config/services.php.
- تغییر ورود مشتری: app/Http/Controllers/CustomerAuthController.php و app/Models/PhoneVerification.php.
- تغییر حریم خصوصی پیگیری سفارش: app/Http/Middleware/EnsureCustomer.php و app/Http/Controllers/OrderTrackingController.php.
- تغییر دسترسی پنل و ورود دومرحله‌ای: routes/web.php، app/Http/Middleware/EnsureAdmin.php، app/Http/Controllers/AdminAuthController.php، app/Http/Controllers/AdminMfaController.php و app/Services/TotpService.php.
- تغییر ساختار دیتابیس: فقط یک migration جدید در database/migrations بساز.
- افزودن/اصلاح تست: فایل Feature مرتبط در tests/Feature.
