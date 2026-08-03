# 🏆 منصة التفوق التعليمية | Al-Tafawwuq Educational Platform

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js" alt="Vue 3" />
  <img src="https://img.shields.io/badge/InertiaJS-1.x-9553E6?style=for-the-badge&logo=inertiajs" alt="InertiaJS" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css" alt="Tailwind CSS" />
</p>

---

## 📖 نبذة عن المشروع / Project Overview

**منصة التفوق** هي منصة تعليمية قطرية تربط الطالب بالمعلم المناسب له. الطالب يختار صفه، فتفتح له مواد المنهج، فيشاهد المعلمين الذين يدرّسون المادة وفيديو تعريفي لكل واحد منهم — ويحجز مع من تناسبه طريقة شرحه، باشتراك شهري في مجموعة أسبوعية أو حصص خاصة.

المنصة مبنية على منهج وزارة التربية والتعليم القطرية: الصفوف من الأول الابتدائي إلى الثاني عشر، مع انقسام المرحلة الثانوية إلى المسارين العلمي والأدبي.

**Al-Tafawwuq** connects students in Qatar with the right teacher. A student picks their grade, which opens the curriculum, which opens the teachers who teach each subject — each with an intro video, so the student can judge the teaching style before committing. Booking is a monthly subscription, either to a weekly group or to private tuition.

Built around the Qatari MOEHE curriculum, grades 1–12, with the secondary stage split into its science and literary tracks.

---

## ✨ المميزات الرئيسية / Key Features

### 🛠️ لوحة تحكم خارقة ومحرر الصفحات التفاعلي (Super Admin Page Editor)
* **تعديل حي وتام لكل صفحات المنصة:** التحكم المطلق في النصوص والروابط والأزرار وقسم الهيرو (Hero Sections) والمعلومات الترحيبية وتنزيل التطبيقات.
* **إدارة النوافذ التنبيهية (Welcome Popups):** إمكانية تفعيل نافذة ترحيبية عامة للطلاب تحتوي على فيديو تعريفي أو إرشادات مع تحكم كامل بالظهور.
* **شريط الحفظ العائم والذكي:** شريط حفظ تفاعلي زجاجي شفاف يسهل عملية حفظ التغييرات أثناء التعديل.

### 🌓 الثيم الذكي والسايدبار المتجاوب (Dynamic Light/Dark Modes)
* **دعم كامل للوضعين الداكن والفاتح** لراحة عين المستخدم مع ألوان مخصصة ومريحة.
* **سايدبار متجاوب كلياً (Dynamic Sidebar):** يتحول تلقائياً إلى اللون الأبيض الأنيق في المظهر الفاتح وإلى الأزرق الداكن الفخم في المظهر المظلم مع تأثيرات هوفر (Hover) واضحة.

### 🧭 رحلة الطالب (Browse & Book)
* **الصف ← المادة ← المعلم:** تصفّح يبدأ من صف الطالب وينتهي ببروفايل المعلم، مع عرض المنهج كاملاً وتمييز المواد التي لم يُسنَد لها معلم بعد.
* **الفيديو التعريفي:** لكل معلم فيديو يعرض طريقة شرحه، وهو ما يبني عليه الطالب قراره قبل الحجز.
* **الاشتراك الشهري:** حجز في مجموعة أسبوعية بسعة محددة، أو حصص خاصة بمواعيد يتفق عليها. الوصول نافذة زمنية تتجدد شهرياً.

### 📚 إدارة المحتوى التعليمي (Educational Content Management)
* **محتوى المجموعة:** فيديوهات وملفات وملازم مرتبطة بكل مجموعة، يشاهدها المشتركون فقط مع إتاحة معاينة مجانية.
* **نظام الحصص والدروس المباشرة (Live Sessions):** غرف بث مباشر بتقنية WebRTC مع سبورة تفاعلية.
* **الاختبارات التفاعلية (Interactive Quizzes):** بنك أسئلة وتصحيح تلقائي على الخادم مع رصد المخالفات، وواجبات يصححها المعلم.

### 💰 المدفوعات والعمولات (Payments & Payouts)
* **بوابات دفع متعددة:** Stripe و Fatora و Tap، بالإضافة إلى التحويل البنكي برفع إيصال يراجعه الأدمن.
* **عمولة لكل معلم:** نسبة قابلة للتخصيص لكل معلم مع تسوية أرباح دورية وإيصالات وإقرار استلام.
* **ولي الأمر:** ربط الأبناء، ومتابعة اشتراكاتهم ودرجاتهم، والدفع نيابة عنهم.

### 🔍 نظام نتائج الطلاب (Student Results Portal)
* محرك بحث سريع للنتائج يتيح للطلاب وأولياء الأمور الاستعلام الفوري عن الدرجات والشهادات بموجب رقم الجلوس أو الرقم القومي.

---

## 🛠️ التقنيات المستخدمة / Tech Stack

* **Back-End:** [Laravel 12](https://laravel.com)
* **Front-End:** [Vue 3](https://vuejs.org) (Composition API) & [InertiaJS](https://inertiajs.com)
* **Styles:** [Tailwind CSS 3](https://tailwindcss.com) (Frosted glassmorphism, responsive elements)
* **Database:** MySQL
* **Live classes:** WebRTC (HTTP-polling signalling — no realtime server needed)
* **Tooling:** Vite, NPM, Pest

### بنية الكود / Code Layout

```
app/Domain/        النماذج والقواعد — Academic · Learning · Scheduling ·
                   Subscription · Payment · Quiz · Communication · User
app/Application/   الخدمات — SubscriptionService · PaymentService · ...
app/Infrastructure/ بوابات الدفع والمراقبون
app/Http/          الكونترولرز فقط
```

> **ملاحظة على النشر:** بيئة البناء على Vercel هي صورة Node ولا تحتوي على `php` أو
> `composer` — الـ runtime هو من يثبّت حزم PHP. لذلك لا تُشغَّل المايجريشن أثناء
> البناء؛ شغّلها مقابل قاعدة بيانات الإنتاج مباشرة:
> `php artisan migrate --force`

---

## 🚀 دليل التثبيت والتشغيل / Installation & Setup

لشغيل المشروع محلياً، اتبع الخطوات التالية:

### 1. المتطلبات الأساسية
* PHP >= 8.2
* Composer
* Node.js & NPM
* MySQL Database

### 2. تنزيل المشروع وتثبيت الحزم
```bash
# استنساخ المستودع
git clone https://github.com/m7mdreda74/edu-system.git
cd edu-system

# تثبيت حزم PHP
composer install

# تثبيت حزم Javascript
npm install
```

### 3. إعداد البيئة وقاعدة البيانات
```bash
# إنشاء ملف البيئة
copy .env.example .env

# توليد مفتاح التطبيق
php artisan key:generate
```
*قم بفتح ملف `.env` وتهيئة اتصالات قاعدة البيانات الخاصة بك (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).*

### 4. الهجرة وتغذية قاعدة البيانات (Migrations & Seeders)
```bash
php artisan migrate --seed
php artisan storage:link
```

### 5. تشغيل السيرفر المحلي
```bash
# تشغيل خادم Laravel (في نافذة منفصلة)
php artisan serve

# تشغيل خادم Vite للتطوير
npm run dev

# أو تجميع الملفات للإنتاج
npm run build
```

---

## 📄 الترخيص / License

هذا المشروع مرخص بموجب رخصة [MIT](LICENSE).

### Vercel file uploads

Vercel Functions have a read-only deployment filesystem, so curriculum files
must use durable object storage in production. Connect a **public Vercel Blob
store** to this project from the Vercel dashboard (Storage → Blob), then
redeploy. New Vercel connections use rotating OIDC credentials automatically
and expose `BLOB_STORE_ID`; no long-lived secret needs to be committed.

The curriculum page then uploads directly from the browser to Blob, avoiding
Vercel's 4.5 MB Function request limit while retaining the application's 25 MB
file limit. Local development continues to use Laravel's `public` disk.

### Scheduled notifications

The production deployment registers two protected Vercel Cron jobs. An hourly
job notifies each confirmed student when a scheduled live class enters the next
24 hours, while a daily job checks active subscriptions and sends a renewal
notification to the student and every verified linked parent when the next
scheduled class is the final class in the current billing period.

Set a random `CRON_SECRET` of at least 16 characters in the Vercel project's
Production environment, then redeploy. Vercel sends the secret in the cron
request's `Authorization` header, and the Laravel endpoint rejects requests
when the secret is missing or does not match. The same checks can be run locally
with `php artisan sessions:send-reminders` and
`php artisan subscriptions:send-renewal-reminders`.
