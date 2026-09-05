# سامانه مدیریت استعلامات

سامانه‌ای کوچک و راست‌به‌چپ برای تعریف و اجرای سرویس‌های استعلامی با Laravel 12 و MySQL 8. ثبت‌نام عمومی وجود ندارد؛ مدیر کاربران را ایجاد می‌کند و به هر کاربر فقط سرویس‌های مجاز را تخصیص می‌دهد.

## امکانات

- دو نقش ثابت `admin` و `user` و جلوگیری کامل از دسترسی کاربر عادی به پنل مدیریت
- ایجاد، ویرایش، غیرفعال‌سازی کاربر و تخصیص چند سرویس به هر کاربر
- تعریف سرویس پویا شامل Endpoint، متد `GET/POST/PUT/PATCH`، نوع Payload (`query/json/form`)، فرمت پاسخ (`json/xml/text`)، Header، Timeout، Retry و محدودیت نرخ
- تعریف نامحدود فیلد ورودی/خروجی با نوع، اجباری بودن، قوانین اعتبارسنجی، گزینه‌های Select، مقدار پیش‌فرض و JSON Path
- اجرای استعلام در صف برای تحمل تعداد زیاد درخواست بدون معطل نگه‌داشتن پردازش‌های وب
- نمایش خودکار نتیجه پس از پایان پردازش، نگهداری تاریخچه همه اجراها و امکان مشاهده پاسخ هر اجرا
- ویرایش ورودی یک درخواست یا اجرای مجدد همان درخواست، بدون حذف نتایج قبلی
- رمزنگاری Headerهای سرویس، ورودی درخواست و پاسخ‌ها در دیتابیس با `APP_KEY`
- ثبت رویدادهای مهم در `audit_logs`، شناسه رهگیری هر درخواست وب و جدول‌های DataTables به‌صورت Server-side
- محافظت در برابر SSRF با Allowlist دامنه، کنترل IPهای خصوصی، الزام HTTPS در Production، توقف Redirect و محدودیت حجم پاسخ

## پیش‌نیازها

- PHP 8.2 یا بالاتر با افزونه‌های `curl`، `mbstring`، `openssl`، `pdo_mysql` و `simplexml`
- MySQL 8 / MariaDB جدید
- Composer 2
- Node.js 20 یا بالاتر

## نصب

```bash
composer install
copy .env.example .env
php artisan key:generate
npm ci
npm run build
```

در Linux/macOS به‌جای `copy` از `cp` استفاده کنید. سپس یک دیتابیس خالی با Collation برابر `utf8mb4_unicode_ci` بسازید و اطلاعات اتصال را در `.env` قرار دهید:

```dotenv
DB_DATABASE=inquiries_gateway
DB_USERNAME=root
DB_PASSWORD=
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=A-Strong-Initial-Password
```

ساخت جداول و مدیر اولیه:

```bash
php artisan migrate --seed
```

نام کاربری مدیر از `ADMIN_EMAIL` و رمز اولیه از `ADMIN_PASSWORD` خوانده می‌شود. در محیط Production، Seeder بدون `ADMIN_PASSWORD` اجرا نخواهد شد.

برای اجرای محلی می‌توانید از دستور زیر استفاده کنید:

```bash
php artisan serve
```

در Apache/Nginx، Document Root باید دقیقاً روی پوشه `public` تنظیم شود؛ خود ریشه پروژه نباید از وب قابل دسترسی باشد.

## پردازش تعداد زیاد درخواست

حالت پیش‌فرض اجرای سرویس‌ها غیرهم‌زمان است. Worker زیر باید همیشه فعال باشد:

```bash
php artisan queue:work --queue=remote-services --sleep=1 --tries=1 --timeout=840
```

در سرور واقعی این دستور را با Supervisor یا systemd دائمی کنید. برای بار بیشتر، چند Worker هم‌زمان اجرا کنید و Queue/Cache را از `database` به Redis انتقال دهید. مقدار `DB_QUEUE_RETRY_AFTER` باید از مجموع Timeout و Retry سرویس‌ها بزرگ‌تر باشد؛ مقدار پیشنهادی پروژه `900` ثانیه است.

برای محیط توسعه بدون Worker می‌توان موقتاً نوشت:

```dotenv
REMOTE_SERVICE_ASYNC=false
QUEUE_CONNECTION=sync
```

## تعریف سرویس مقصد

Seeder کاتالوگ، ۱۵ سرویس پایه استعلامی و ورودی‌های استاندارد آن‌ها را به‌صورت idempotent ایجاد می‌کند. Endpoint اولیه این رکوردها عمداً روی دامنه رزروشده `inquiries.invalid` قرار دارد؛ پیش از فعال‌کردن استفاده عملیاتی، مدیر باید Endpoint واقعی هر سرویس را در پنل مدیریت ثبت کند. اجرای دوباره Seeder، تنظیمات ارتباطی تغییرکرده توسط مدیر را بازنویسی نمی‌کند.

مدیر از بخش «سرویس‌ها» این موارد را وارد می‌کند:

1. آدرس Endpoint و متد HTTP
2. نحوه ارسال پارامترها و فرمت پاسخ
3. Headerهای لازم مانند `Authorization` به‌صورت JSON
4. فیلدهای ورودی کاربر و قوانین اعتبارسنجی
5. فیلدهای خروجی و مسیر آن‌ها در پاسخ؛ نمونه: `data.person.name`

Headerهای `Host`، `Content-Length`، `Transfer-Encoding` و `Connection` عمداً قابل تعریف نیستند. Redirect سرویس مقصد نیز دنبال نمی‌شود؛ آدرس نهایی باید مستقیماً در Endpoint ثبت شود.

در Production بهتر است دامنه‌های مجاز را صریح تعیین کنید:

```dotenv
REMOTE_SERVICE_ALLOWED_HOSTS=api.example.com,*.trusted.example
REMOTE_SERVICE_ALLOW_PRIVATE_NETWORKS=false
REMOTE_SERVICE_REQUIRE_HTTPS=true
REMOTE_SERVICE_ENFORCE_DNS_RESOLUTION=true
REMOTE_SERVICE_MAX_RESPONSE_BYTES=1048576
```

اگر سرویس مقصد داخل شبکه سازمانی است، فقط پس از بررسی شبکه مقدار `REMOTE_SERVICE_ALLOW_PRIVATE_NETWORKS=true` را فعال و Allowlist دامنه را محدود کنید.

## دیتابیس

منبع اصلی ساختار دیتابیس Migrationهای پوشه `database/migrations` است. فایل `database/schema/mysql.sql` نیز نسخه کامل SQL (همراه جدول سوابق Migration) برای ایجاد دستی جداول است؛ پس از Import فقط `php artisan db:seed` را اجرا کنید. جدول‌های مهم:

- `users`: کاربران و نقش
- `services`: تنظیمات ارتباط با سرویس مقصد
- `service_fields`: ورودی‌ها و خروجی‌های پویا
- `service_user`: دسترسی هر کاربر به سرویس
- `service_requests`: رکورد اصلی درخواست و آخرین وضعیت
- `service_request_attempts`: Snapshot ورودی و نتیجه هر بار اجرا
- `audit_logs`: ردپای عملیات حساس
- `jobs`, `failed_jobs`, `cache`, `sessions`: زیرساخت صف، محدودیت نرخ و نشست

فیلدهای رمزنگاری‌شده فقط با همان `APP_KEY` قابل خواندن هستند. از `.env` و به‌ویژه `APP_KEY` نسخه پشتیبان امن بگیرید؛ تغییر یا گم‌شدن کلید، داده‌های قبلی را غیرقابل بازیابی می‌کند.

## آزمون و کنترل کیفیت

```bash
php artisan test
composer audit
npm audit
```

آزمون‌ها از SQLite در حافظه و `.env.testing` استفاده می‌کنند و به دیتابیس اصلی دست نمی‌زنند.

## تنظیمات Production

- `APP_ENV=production`، `APP_DEBUG=false` و `APP_URL` مبتنی بر HTTPS
- اجرای `php artisan config:cache` و `php artisan route:cache` پس از نهایی‌شدن `.env`
- فعال‌بودن Worker صف و مانیتورکردن `failed_jobs`
- پشتیبان‌گیری منظم از دیتابیس و `APP_KEY`
- محدودکردن `REMOTE_SERVICE_ALLOWED_HOSTS`
- نگهداری پروژه در مسیر غیرعمومی و دسترسی وب فقط به `public`
