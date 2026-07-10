# AlTafawwuq (التفوق) — Educational Platform — Agentic Build Prompt

## دورك (Role)
أنت مهندس Full-Stack خبير في Laravel و Vue.js، مطلوب منك بناء منصة تعليمية إلكترونية كاملة اسمها **"التفوق" (AlTafawwuq)** — منصة لتدريس مواد المرحلة الثانوية (التوجيهي) بنظام الكورسات المدفوعة، مشابهة في الفكرة لمنصات مثل almusaed-qa.com، مع دعم كامل للغة العربية (RTL) والإنجليزية.

ابنِ المشروع بالكامل: الـ Backend، الـ Frontend، قاعدة البيانات، والتوثيق، خطوة بخطوة، مع تشغيل الأوامر فعليًا والتأكد من نجاح كل خطوة قبل الانتقال للتالية.

---

## 0. معايير الجودة الهندسية الإلزامية (Non-Negotiable Engineering Standards)

هذا القسم **إلزامي وله أولوية على السرعة** — أي كود يتكتب في المشروع لازم يلتزم بيه من أول سطر، مش تحسين لاحق:

### 0.1 SOLID Principles
- **S (Single Responsibility):** كل Class له مسؤولية واحدة فقط. مثلاً: `Controller` يستقبل الـ request ويرجّع response بس، والـ logic الفعلي يروح في `Service` أو `Action` class منفصل.
- **O (Open/Closed):** صمّم الكلاسات عشان تتوسّع من غير ما تعدّل كود موجود — مثال: `PaymentGatewayInterface` (اتذكر في قسم المدفوعات) بيسمح بإضافة بوابة جديدة من غير ما تلمس الكود القديم.
- **L (Liskov Substitution):** أي Subclass لازم يقدر يحل محل الـ parent class من غير ما يكسر السلوك المتوقع.
- **I (Interface Segregation):** متعملش Interface ضخم فيه ميثودز مش كل الكلاسات محتاجاها — قسّمه لـ Interfaces أصغر ومحددة.
- **D (Dependency Inversion):** الكلاسات تعتمد على Abstractions (Interfaces) مش على Concrete classes — استخدم Laravel Service Container للـ Binding.

### 0.2 Design Patterns المطلوب استخدامها
- **Repository Pattern:** لفصل منطق الوصول لقاعدة البيانات عن الـ business logic (مثال: `CourseRepositoryInterface` + `EloquentCourseRepository`)
- **Service Layer Pattern:** كل عملية معقدة (شراء كورس، نشر تسجيل حصة، إصدار فاتورة) تتكتب في `Service class` مخصص، مش جوه الـ Controller
- **Factory Pattern:** لإنشاء الكائنات المعقدة (مثلاً اختيار بوابة الدفع المناسبة وقت التشغيل حسب `.env`)
- **Observer Pattern:** استخدم Laravel Events & Listeners للأحداث الجانبية (مثل: عند اكتمال الدفع → إرسال إشعار + إنشاء enrollment + إصدار فاتورة، كل واحدة Listener منفصل مش كله في مكان واحد)
- **Strategy Pattern:** لحساب نسبة العمولة أو لتحديد نوع الإشعار المناسب حسب حالة معينة
- **DTO (Data Transfer Objects):** استخدم DTOs لنقل البيانات بين الـ Layers بدل تمرير Arrays أو Request objects مباشرة للـ Service layer

### 0.3 Clean Architecture — تقسيم الطبقات

نظّم كود الـ `app/` بالشكل ده بدل الطريقة الافتراضية المسطحة:

```
app/
├── Domain/                  ← الكيانات الأساسية والـ Business Rules (لا تعتمد على Laravel)
│   ├── Course/
│   │   ├── Entities/
│   │   └── Contracts/ (Interfaces)
│   └── Payment/
├── Application/              ← Services و Use Cases (منطق التطبيق)
│   ├── Services/
│   ├── Actions/
│   └── DTOs/
├── Infrastructure/           ← التنفيذ الفعلي (Eloquent, بوابات الدفع, SDKs خارجية)
│   ├── Repositories/
│   └── Gateways/
└── Http/                     ← Controllers, Requests, Resources (طبقة العرض فقط)
```

**القاعدة الذهبية:** الـ Controller ميعرفش حاجة عن Eloquent مباشرة — بيتكلم مع Service، والـ Service بيتكلم مع Repository Interface، مش مع الـ Model مباشرة.

### 0.4 Clean Code — قواعد صارمة
- أسماء الدوال والمتغيرات وصفية وكاملة (`calculateTeacherPayout()` مش `calc()`)
- كل دالة تعمل حاجة واحدة بس، وطولها المفضل أقل من 20 سطر
- تجنب الـ Nested if/else العميق — استخدم Early Return / Guard Clauses
- Magic Numbers ممنوعة — استخدم Constants أو Enums (مثال: `enum PaymentStatus: string { case Pending = 'pending'; case Paid = 'paid'; }`)
- Comments بس للشرح "ليه" مش "إيه" — لو الكود محتاج comment يشرح إيه بيعمل، يبقى لازم تعيد تسميته بدل ما تشرحه

### 0.5 الأداء (Performance)
- تجنب N+1 Queries: استخدم `with()`/`load()` Eager Loading دايمًا مع العلاقات
- Cache للبيانات اللي بتتغير نادرًا (قائمة المواد الدراسية، إعدادات المنصة) باستخدام `Cache::remember()`
- Pagination إجباري لأي قائمة (كورسات، طلاب، مدفوعات) — ممنوع `get()` على جداول كبيرة
- Index على أي عمود بيتفلتر أو يترتب عليه كتير (اتذكر في Database Schema)
- استخدم Queue لأي عملية تقيلة أو بطيئة (إرسال إيميلات، توليد PDF، معالجة webhooks) — مفيش عملية I/O تقيلة في نفس الـ request
- استخدم `select()` لجلب الأعمدة المطلوبة بس بدل `SELECT *` في الاستعلامات الكبيرة
- ضغط واستخدام lazy loading للصور في الـ Frontend (خصوصًا صفحة الكورسات اللي فيها صور كتير)

### 0.6 تجنب الأخطاء المنطقية (Avoiding Logical Errors)
- أي عملية مالية (دفع، عمولة، استرجاع) لازم تتغلف بـ `DB::transaction()` عشان تضمن الـ Atomicity — لو خطوة فشلت، كل حاجة ترجع زي ما كانت
- Validation مزدوج: في الـ Frontend (UX) وفي الـ Backend (أمان) — منعتمدش على validation الـ Frontend وحده أبدًا
- استخدم `strict types` في PHP (`declare(strict_types=1);`) في كل ملف جديد
- اكتب Feature Tests للسيناريوهات الحرجة **قبل ما تعتبر أي Feature خلصت**: هل الطالب يقدر يشتري نفس الكورس مرتين؟ هل ممكن الـ progress يتجاوز 100%؟ هل ممكن الاختبار يتحل أكتر من مرة المسموح؟
- تحقق من الـ Authorization في كل Endpoint (Policies) — مش بس Authentication: مثلاً تأكد إن المعلم ميقدرش يشوف بيانات كورس مش بتاعه
- استخدم Laravel Form Requests لكل validation logic معقد، وارجع رسائل خطأ واضحة ومحددة

---

## 1. Tech Stack (البنية التقنية)

- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** Vue.js 3 (Composition API) + Inertia.js (SPA بدون API منفصل، أو REST API + Vue SPA منفصل — اختر Inertia للتكامل الأسرع)
- **Database:** MySQL 8
- **Auth:** Laravel Sanctum (لو REST API منفصل) أو Laravel Breeze/Inertia Auth
- **Styling:** Tailwind CSS 3 + دعم RTL كامل
- **Realtime:** Laravel Echo + Pusher/Reverb (للإشعارات والشات)
- **Video Streaming:** تصميم بنية تسمح بدمج Bunny Stream أو Mux لاحقًا (حماية الفيديو من التحميل المباشر)
- **Payment Gateway:** بنية Abstract Payment Service تسمح بإضافة Stripe / Fatora / أي بوابة محلية لاحقًا
- **Queue:** Laravel Queue (Redis أو Database driver) للإشعارات والإيميلات
- **Testing:** Pest أو PHPUnit للـ Feature Tests الأساسية

### 1.1 دور Inertia.js في المعمارية (مهم جدًا — التزم به بدقة)

Inertia.js **مش REST API ومش framework مستقل** — هي طبقة ربط (adapter) بين Laravel Controllers وVue Components، بتسمح ببناء SPA حقيقي (تنقل بدون reload) من غير الحاجة لبناء API endpoints منفصلة أو التعامل مع JWT/CORS.

**آلية العمل المطلوبة في كل Controller:**

```php
// طبقة Http فقط — لا تضع أي business logic هنا
class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    public function index()
    {
        return Inertia::render('Courses/Index', [
            'courses' => $this->courseService->getPublishedCourses(),
        ]);
    }
}
```

```vue
<!-- resources/js/Pages/Courses/Index.vue -->
<script setup>
defineProps({ courses: Array })
</script>
```

**قاعدة إلزامية للـ Clean Architecture (اربطها بقسم 0.3):**
Inertia تتواجد **في طبقة الـ Http فقط**. الـ Controller يستدعي `Service` من طبقة الـ Application، والـ Service يرجّع نتيجة (يفضّل تكون DTO)، والـ Controller بس هو اللي يحوّلها لـ props ويبعتها عبر `Inertia::render()`. طبقات الـ Domain والـ Application **يُمنع تمامًا** أن تعرف بوجود Inertia أو أي تفاصيل HTTP — بهذا الشكل، لو احتجت مستقبلًا تبني REST API إضافي لتطبيق موبايل، مش هتلمس أي business logic، هتضيف بس Controllers جديدة في طبقة الـ Http بتتكلم مع نفس الـ Services.

**لماذا Inertia هي الاختيار الصحيح هنا (وليس REST API + SPA منفصل):**
- بناء أسرع: Controller واحد بدل API layer + state management منفصل في الفرونت
- Authentication تلقائي عبر Laravel Sessions (بدون Sanctum/JWT معقد)
- SEO أفضل بفضل الـ server-side render الأولي للصفحة
- لا حاجة حاليًا لتطبيق موبايل native ضمن أولويات المشروع

**تعليمات صريحة للـ Agent:**
- استخدم `Inertia::render()` في كل الـ Controllers الخاصة بالصفحات (Public, Student, Teacher, Admin)
- استخدم Inertia Form Helper (`useForm()` من `@inertiajs/vue3`) للتعامل مع الفورمات وعرض أخطاء الـ Validation تلقائيًا
- استخدم `Inertia::share()` في `HandleInertiaRequests` middleware لمشاركة بيانات عامة (المستخدم الحالي، الإشعارات غير المقروءة) مع كل الصفحات بدل تكرارها في كل Controller
- عند الحاجة لتحديث جزء من الصفحة فقط (مثال: عداد الإشعارات) استخدم Partial Reloads (`only: []`) بدل إعادة تحميل كل الـ props

---

## 3. الأدوار والمستخدمين (User Roles)

استخدم Spatie Laravel-Permission لإدارة الأدوار:

1. **Student (طالب):** يتصفح الكورسات، يشتري، يتابع تقدمه، يحل الاختبارات
2. **Teacher (معلم):** يرفع كورسات، يدير محتواه، يشوف تقارير طلابه وأرباحه
3. **Admin (أدمن):** يدير كل المنصة، المستخدمين، المدفوعات، الموافقة على الكورسات

---

## 4. قاعدة البيانات (Database Schema)

أنشئ الـ Migrations التالية بالترتيب:

```
users (id, name, email, phone, password, role, avatar, grade_level, is_active)
subjects (id, name, name_en, grade_level, icon)
courses (id, teacher_id, subject_id, title, slug, description, thumbnail,
         price, discount_price, is_published, total_duration, level)
course_lessons (id, course_id, title, video_url, duration_seconds,
                order, is_free_preview, attachment_path)
enrollments (id, user_id, course_id, progress_percent, enrolled_at, completed_at)
lesson_progress (id, enrollment_id, lesson_id, watched_seconds, is_completed)
quizzes (id, course_id, lesson_id, title, passing_score, time_limit_minutes)
quiz_questions (id, quiz_id, question_text, type, order)
quiz_options (id, question_id, option_text, is_correct)
quiz_attempts (id, user_id, quiz_id, score, passed, attempted_at)
payments (id, user_id, course_id, amount, currency, gateway, gateway_ref,
          status, paid_at)
reviews (id, user_id, course_id, rating, comment, is_approved)
notifications (Laravel's default notifications table)
coupons (id, code, discount_percent, expires_at, usage_limit, used_count)
```

**تأكد من:**
- كل الجداول فيها `soft deletes` حيث يلزم
- Foreign keys مع `onDelete('cascade')` أو `set null` حسب المنطق
- Indexes على الأعمدة اللي هتتفلتر عليها كتير (slug, grade_level, is_published)

---

## 5. الصفحات المطلوبة (Frontend Pages)

### صفحات عامة (Public)
- `/` — الرئيسية: عرض أحدث الكورسات، أشهر المدرسين، بانرات
- `/courses` — كل الكورسات مع فلاتر (المادة، الصف الدراسي، السعر، التقييم)
- `/courses/{slug}` — تفاصيل الكورس: المحاضر، المنهج، عينة فيديو مجانية، التقييمات، زر شراء
- `/teachers/{id}` — بروفايل المدرس وكل كورساته
- `/login` `/register` — تسجيل دخول/حساب جديد

### صفحات الطالب (بعد تسجيل الدخول)
- `/dashboard` — لوحة تحكم الطالب: كورساته، نسبة تقدمه
- `/my-courses/{slug}/learn` — صفحة مشاهدة الدرس (فيديو + قائمة دروس + مرفقات)
- `/my-courses/{slug}/quiz/{id}` — صفحة الاختبار
- `/profile` — البيانات الشخصية والفواتير

### صفحات المعلم
- `/teacher/dashboard` — إحصائيات: عدد الطلاب، الأرباح، آخر التقييمات
- `/teacher/courses/create` — إنشاء كورس جديد
- `/teacher/courses/{id}/lessons` — إدارة دروس الكورس (رفع فيديو، ترتيب)

### لوحة تحكم الأدمن
- `/admin/dashboard` — إحصائيات عامة
- `/admin/courses` — الموافقة على الكورسات المرفوعة
- `/admin/users` — إدارة المستخدمين والأدوار
- `/admin/payments` — متابعة المدفوعات والمبالغ المستحقة للمدرسين

---

## 6. المميزات الوظيفية الأساسية (Core Features)

1. **نظام شراء الكورسات:** سلة شراء + كوبونات خصم + بوابة دفع (ابدأ بـ Stripe test mode)
2. **حماية الفيديو:** امنع التحميل المباشر، استخدم signed URLs مؤقتة لكل فيديو
3. **تتبع التقدم:** progress bar تلقائي بناءً على `lesson_progress`
4. **الاختبارات:** بعد كل درس أو نهاية الكورس، مع نتيجة فورية
5. **الإشعارات:** كورس جديد، رد على تقييم، اكتمال الكورس (Laravel Notifications)
6. **التقييمات:** يقدر الطالب يقيّم الكورس بعد إكماله فقط
7. **الشهادات:** توليد شهادة PDF تلقائيًا عند إكمال الكورس بنجاح (استخدم dompdf أو browsershot)
8. **الداشبورد التحليلي للمعلم:** رسم بياني للمبيعات والطلاب (Chart.js)

---

## 7. متطلبات التصميم (UI/UX)

- دعم RTL كامل (اللغة الأساسية عربي)
- Design system: ألوان أساسية جادة ومريحة للتعليم (أزرق/أخضر تركوازي)
- خط Cairo أو Tajawal للعربي، Inter للإنجليزي
- Responsive بالكامل (Mobile-first، لأن غالبية الطلاب هيستخدموا موبايل)
- Dark mode اختياري

---

## 8. خطوات التنفيذ (Execution Steps)

نفّذ بالترتيب ده، وتأكد من نجاح كل خطوة قبل الانتقال للتالية:

1. `laravel new altafawwuq` + إعداد `.env` مع MySQL
2. تثبيت Breeze مع Vue + Inertia: `laravel breeze:install vue`
3. تثبيت Spatie Permission + عمل الـ Roles الأساسية (seeder)
4. إنشاء كل الـ Migrations والـ Models مع العلاقات (relationships) كاملة
5. إنشاء الـ Factories والـ Seeders لبيانات تجريبية واقعية (10 كورسات، 5 مدرسين، 20 طالب)
6. بناء الـ Controllers والـ Routes للصفحات العامة أولاً
7. بناء صفحات Vue للـ public pages مع Tailwind
8. بناء نظام الشراء والدفع (mock gateway في البداية)
9. بناء صفحة مشاهدة الدرس مع تتبع التقدم
10. بناء لوحة تحكم المعلم والأدمن
11. بناء نظام الشات بين الطالب والمعلم (conversations + chat_messages) مع Laravel Echo
12. دمج SDK البث المباشر (Agora/100ms) + جدولة الحصص + Webhook استقبال رابط التسجيل + خاصية "نشر كدرس"
13. دمج بوابة الدفع (ابدأ بـ Stripe test mode ثم أضف Fatora/Skip Cash) مع نظام العمولة والفواتير
14. كتابة Feature tests أساسية للمسارات الحرجة (تسجيل، شراء، مشاهدة درس، دخول حصة مباشرة)
15. مراجعة نهائية: تأكد إن كل صفحة شغالة بدون أخطاء، وإن الـ RTL شغال صح في كل مكان

---

## 9. الحصص المباشرة (Live Classes) + الشات + التسجيل

### 9.1 الفكرة
المعلم يقدر يعمل "حصة مباشرة" (Live Session) مجدولة، الطلاب المشتركين في الكورس يدخلوا يتابعوها لايف، وبعد الحصة تتسجل تلقائيًا وتتنشر كدرس عادي في الكورس (Recorded Lesson) لو المعلم وافق على النشر.

### 9.2 قاعدة البيانات الإضافية

```
live_sessions (id, course_id, teacher_id, title, description,
               scheduled_at, started_at, ended_at, status[scheduled|live|ended],
               room_id, recording_url, is_published_as_lesson, lesson_id)

live_session_attendees (id, live_session_id, user_id, joined_at, left_at)

chat_messages (id, conversation_id, sender_id, message, attachment_path,
               is_read, created_at)

conversations (id, course_id, student_id, teacher_id, last_message_at)
```

### 9.3 التقنية المقترحة للبث المباشر

اختر واحدة حسب الميزانية:

| الخيار | الوصف | التكلفة |
|---|---|---|
| **Agora.io / Zoom SDK** | جاهز، فيه تسجيل تلقائي، سهل الدمج | مدفوع بعد حد معين |
| **Mediasoup / WebRTC self-hosted** | مجاني لكن يحتاج سيرفر خاص وضبط تقني أعلى | تكلفة سيرفر فقط |
| **100ms.live** | بديل بسيط بـ SDK لـ Vue، فيه تسجيل سحابي جاهز | Free tier محدود |

**التوصية:** ابدأ بـ Agora.io أو 100ms في مرحلة الـ MVP، لأن عندهم Cloud Recording جاهز بدون ما تبني بنية WebRTC بنفسك.

### 9.4 تدفق العمل (Flow)

1. المعلم يجدول حصة من `/teacher/courses/{id}/live-sessions/create` (عنوان، تاريخ، وقت)
2. قبل الموعد بدقائق، يظهر زرار "دخول الحصة" لكل من المعلم والطلاب المشتركين في الكورس فقط
3. عند بدء الحصة: `status = live` + تفعيل الـ Cloud Recording تلقائيًا من الـ SDK
4. أثناء الحصة: شات نصي مباشر بين الطلاب والمعلم (Laravel Echo + Pusher/Reverb لإشعارات الدخول/الخروج، والـ SDK نفسه بيدي شات مدمج غالبًا)
5. عند انتهاء الحصة: `status = ended` + استقبال webhook من مزود البث بيدي رابط التسجيل → يتخزن في `recording_url`
6. المعلم يشوف التسجيل في لوحة تحكمه، ويقدر يضغط "نشر كدرس" → ده بينشئ `course_lesson` جديد مربوط بالتسجيل، ويظهر لكل الطلاب كأي درس عادي

### 9.5 الشات العام (بين طالب ومعلم خارج الحصص)

- محادثة واحدة لكل (طالب + معلم + كورس) — جدول `conversations`
- رسائل نصية + مرفقات (صور/ملفات) — جدول `chat_messages`
- Real-time عبر Laravel Echo + Pusher/Reverb، مع إشعار للطرف التاني لو مش متصل حاليًا
- عداد رسائل غير مقروءة في الـ Navbar

---

## 10. نظام المدفوعات بالتفصيل (Payment System)

### 10.1 مبدأ التصميم: Payment Gateway Abstraction

اعمل `interface PaymentGatewayInterface` بميثودين أساسيين:

```php
interface PaymentGatewayInterface {
    public function createPaymentIntent(float $amount, string $currency, array $meta): PaymentIntentResult;
    public function verifyPayment(string $reference): PaymentVerificationResult;
}
```

وبعدين اعمل implementation لكل بوابة: `StripeGateway`, `FatoraGateway` (قطر), `SkipCashGateway` (قطر بديل)، واستخدم Service Container عشان تبدّل بينهم بسهولة من الـ `.env`:

```
PAYMENT_GATEWAY=stripe   # أو fatora / skipcash
```

### 10.2 بوابات محلية مقترحة (لو المنصة قطرية)

| البوابة | ملاحظات |
|---|---|
| **Fatora.io** | بوابة قطرية، بتدعم كروت محلية ودولية، API واضح وبسيط |
| **Skip Cash** | بديل قطري تاني، مستخدم في كتير من المنصات المحلية |
| **Stripe** | لو المنصة هتستهدف طلاب برضو برا قطر/الخليج |

### 10.3 تدفق الدفع الكامل

```
courses (السلة) → checkout → إنشاء "payment intent" عبر الـ Gateway المختار
   → توجيه المستخدم لصفحة الدفع (Redirect أو Embedded Widget)
   → استلام Webhook من البوابة بتأكيد الدفع
   → تحديث حالة payments.status = 'paid'
   → إنشاء enrollment تلقائيًا للطالب في الكورس
   → إرسال إشعار + فاتورة PDF بالإيميل
```

### 10.4 جداول إضافية للمدفوعات

```
invoices (id, payment_id, invoice_number, pdf_path, issued_at)
teacher_payouts (id, teacher_id, amount, period_start, period_end,
                 status[pending|paid], paid_at)
platform_settings (commission_percent)  -- نسبة عمولة المنصة من كل عملية بيع
```

### 10.5 نظام العمولة (Commission)

- كل عملية شراء، المنصة تاخد نسبة (مثلاً 20%) والباقي يتحسب كمستحقات للمعلم في `teacher_payouts`
- الأدمن من لوحة التحكم يقدر يعمل "صرف مستحقات" شهري للمدرسين (يدوي في البداية، أو عبر تحويل بنكي API لاحقًا)

### 10.6 الأمان (Security Requirements)

- لا تخزن أي بيانات كارت خام أبدًا — كله عبر الـ Gateway (PCI-DSS compliance تلقائي)
- تحقق دايمًا من الـ Webhook signature قبل تحديث حالة أي دفعة
- استخدم Laravel Queue لمعالجة الـ Webhooks (idempotent) عشان تتجنب تكرار الـ enrollment لو الـ webhook اتبعت مرتين

---

## 11. نظام الإشعارات بالتفصيل (Notifications System)

### 11.1 القنوات (Channels)
استخدم Laravel Notification Channels متعددة لكل نوع إشعار:
- **In-app:** جرس إشعارات في الـ Navbar (realtime عبر Laravel Echo)
- **Email:** لحظات مهمة فقط (تأكيد شراء، فاتورة، تذكير بحصة مباشرة)
- **Push Notification:** عبر Firebase Cloud Messaging (لو فيه تطبيق موبايل مستقبلًا) أو Web Push
- **SMS:** اختياري لتذكير الحصص المباشرة قبل موعدها بساعة (عبر بوابة محلية زي Twilio أو مزود قطري)

### 11.2 جدول تفضيلات الإشعارات

```
notification_preferences (id, user_id, notification_type, channel, is_enabled)
```

يخلي المستخدم يتحكم مثلاً: "وقف إشعارات الإيميل بس سيب الـ in-app شغالة"

### 11.3 أنواع الإشعارات المطلوبة (Notification Types)

| النوع | المستقبِل | القناة الافتراضية |
|---|---|---|
| كورس جديد من مدرس بتتابعه | طالب | In-app + Email |
| حصة مباشرة هتبدأ بعد 15 دقيقة | طالب مسجل في الكورس | In-app + Push + SMS |
| رد على سؤالك في الشات | طالب/معلم | In-app + Push |
| نجاح عملية الدفع | طالب | Email (فاتورة PDF) |
| كورسك جديد محتاج مراجعة الأدمن | معلم | In-app + Email |
| تمت الموافقة على كورسك | معلم | In-app + Email |
| تقييم جديد على كورسك | معلم | In-app |
| مستحقاتك المالية اتحولت | معلم | Email |
| اقتربت نهاية مدة الاشتراك/الكورس | طالب | In-app + Email |

### 11.4 قاعدة إلزامية
كل الإشعارات تتبعت عبر **Laravel Queue** (Observer Pattern المذكور في قسم 0.2) — ممنوع إرسال إشعار بشكل synchronous جوه الـ request نفسه.

---

## 12. الشيتات (Worksheets/Study Sheets)

### 12.1 الفكرة
منفصلة عن الفيديو تمامًا — المعلم يرفع "شيت" (PDF مذاكرة أو واجب) مرتبط بدرس أو بالكورس ككل، والطالب يقدر يحمّله ويحل فيه، وبعض الشيتات ممكن تتطلب تسليم إجابة يراجعها المعلم يدويًا.

### 12.2 قاعدة البيانات

```
worksheets (id, course_id, lesson_id, title, file_path, type[study|homework],
            requires_submission, due_date, max_score)

worksheet_submissions (id, worksheet_id, student_id, submitted_file_path,
                        score, teacher_feedback, submitted_at, graded_at)
```

### 12.3 تدفق العمل
1. المعلم يرفع شيت من صفحة إدارة الدرس، يحدد لو محتاج تسليم أو للمذاكرة بس
2. الطالب يشوف الشيت في صفحة الدرس، يحمّله، ولو محتاج تسليم يرفع إجابته
3. المعلم يشوف كل التسليمات في `/teacher/worksheets/{id}/submissions`، يحط درجة و feedback
4. إشعار تلقائي للطالب لما يتصحح الشيت (استخدم نظام الإشعارات في قسم 11)

---

## 13. تفصيل الاختبارات وحماية من الغش (Quizzes/Exams + Anti-Cheat)

### 13.1 التمييز بين الأنواع

عدّل جدول `quizzes` ليشمل:

```
quizzes (id, course_id, lesson_id, title,
         quiz_type[practice_quiz|final_exam],   -- كويز سريع تقييمي أو امتحان رسمي
         passing_score, time_limit_minutes,
         max_attempts,                           -- 0 = غير محدود، 1 = محاولة وحيدة (للامتحانات الرسمية)
         shuffle_questions, shuffle_options,
         randomize_from_pool)                    -- سحب عدد أسئلة عشوائي من بنك أسئلة أكبر
```

### 13.2 أنواع الأسئلة المدعومة
- اختيار من متعدد (Multiple Choice) — تصحيح تلقائي
- صح/خطأ (True/False) — تصحيح تلقائي
- مقالي (Essay) — يحتاج تصحيح يدوي من المعلم، جدول `quiz_essay_answers (id, attempt_id, question_id, answer_text, score, feedback)`

### 13.3 آليات منع الغش (Anti-Cheat Measures)
- **منع فتح تبويب تاني:** استخدم `visibilitychange` event في الـ Frontend، وسجّل أي محاولة خروج في جدول `quiz_attempt_violations (id, attempt_id, violation_type, occurred_at)`
- **منع النسخ/اللصق:** تعطيل `oncopy`/`oncontextmenu` في صفحة الامتحان (JS-level، تحسين تجربة مش حماية مطلقة)
- **Timer إجباري من السيرفر لا الفرونت:** احسب الوقت المتبقي من `started_at` المخزّن في الـ Backend، مش من متغير JS، عشان تمنع التلاعب بالوقت من الـ DevTools
- **قفل تلقائي عند تجاوز عدد المخالفات:** لو الطالب خرج من التبويب أكتر من X مرة، أنهي الامتحان تلقائيًا وسجّله كمخالفة للمراجعة الإدارية
- **محاولة واحدة للامتحانات الرسمية:** تحقق من `max_attempts` قبل السماح بالدخول، وارفض أي محاولة تانية بـ Policy واضح

---

## 14. حسابات أولياء الأمور (Parent Accounts)

### 14.1 الفكرة
ولي الأمر يعمل حساب مرتبط بحساب ابنه/ابنته الطالب، يقدر يتابع بس (View-only) بدون أي صلاحية تعديل.

### 14.2 قاعدة البيانات

```
parent_student_links (id, parent_user_id, student_user_id, relationship,
                       verified_at)
```

يتحقق الربط عبر: المدرسة/رقم الطالب أو كود دعوة يبعته النظام لولي الأمر بالإيميل/SMS.

### 14.3 لوحة تحكم ولي الأمر (View-Only)
- تقدم الطالب في كل كورس (progress %)
- نتائج الاختبارات والامتحانات
- الحضور في الحصص المباشرة
- الفواتير والمدفوعات (شفافية مالية)
- إشعار أسبوعي تلقائي (Email مُجمّع) بملخص الأداء

**قاعدة أمان مهمة:** Policy صارم يمنع ولي الأمر من أي عملية كتابة (شراء، رد في شات، حل اختبار) — قراءة فقط بشكل مطلق.

---

## 15. الحضور وتقارير الانتظام (Attendance & Engagement Reports)

### 15.1 بناءً على `live_session_attendees` الموجود، أضف تحليل تلقائي:

```
attendance_summary (view/query مبني على live_session_attendees وليس جدول منفصل):
  - نسبة حضور كل طالب في الكورس = (عدد الحصص اللي حضرها / إجمالي الحصص المجدولة) * 100
  - مدة الحضور الفعلية = (left_at - joined_at) مقارنة بمدة الحصة الكاملة (كشف "الدخول والخروج فورًا")
```

### 15.2 تقرير للمعلم
`/teacher/courses/{id}/attendance` — جدول فيه كل طالب ونسبة حضوره، مع تنبيه أحمر للطلاب اللي نسبة حضورهم أقل من حد معين (مثلاً 50%)

---

## 16. البحث (Search)

### 16.1 التقنية
استخدم **Laravel Scout** مع driver مناسب:
- **Meilisearch** (مجاني، سريع، دعم ممتاز للعربي) — التوصية الأساسية
- بديل: **Algolia** لو الميزانية تسمح ومحتاج SaaS جاهز

### 16.2 الحقول المفهرسة (Searchable)
- الكورسات: العنوان، الوصف، اسم المادة، اسم المدرس
- المدرسين: الاسم، التخصص
- دعم البحث بالعربي مع تجاهل التشكيل والهمزات المختلفة (Meilisearch بيدعم ده بشكل جيد افتراضيًا)

### 16.3 واجهة البحث
Search bar في الـ Navbar مع Autocomplete (نتائج فورية أثناء الكتابة عبر API endpoint خفيف مخصص للبحث)

---

## 17. منع مشاركة الحساب / إدارة الأجهزة (Device & Session Management)

### 17.1 المشكلة التجارية
طالب واحد يشتري كورس ويشارك بيانات دخوله مع أصدقائه — بيقلل الإيرادات.

### 17.2 الحل التقني

```
user_sessions (id, user_id, device_id, device_name, ip_address,
               last_active_at, is_current)
```

### 17.3 القواعد
- حدد حد أقصى لعدد الأجهزة المسموحة في نفس الوقت (مثلاً جهازين: موبايل + لابتوب)
- عند تسجيل الدخول من جهاز جديد يتخطى الحد، اعرض للمستخدم قائمة أجهزته الحالية ويختار يقفل واحد منهم (زي Netflix)
- **حماية إضافية أثناء مشاهدة الفيديو تحديدًا:** امنع تشغيل نفس الفيديو من جهازين في نفس اللحظة بالظبط (اختياري، أعلى تشددًا) عبر تتبع `active_video_sessions` مؤقت في الـ Cache (Redis) بـ TTL قصير

---

## 18. تقارير تحليلية متقدمة للأدمن (Advanced Admin Analytics)

### 18.1 لوحة تحكم تحليلية `/admin/analytics`

| المؤشر | الوصف |
|---|---|
| الإيرادات الشهرية | رسم بياني خطي بالإيرادات آخر 12 شهر |
| أكتر الكورسات مبيعًا | Top 10 مع عدد المبيعات والإيراد |
| معدل إكمال الكورسات (Completion Rate) | نسبة الطلاب اللي خلصوا الكورس من إجمالي المسجلين |
| معدل الارتداد (Churn) | طلاب اشتروا كورس واحد وملرجعوش يشتروا تاني خلال 3 شهور |
| متوسط تقييم كل مادة/مدرس | لمساعدة الأدمن في قرارات الموافقة على مدرسين جدد |
| أكتر الأوقات نشاطًا | لتحديد أفضل وقت لجدولة الحصص المباشرة الجديدة |

### 18.2 التنفيذ التقني
- استخدم Query مجمّعة (aggregation) مع Cache قصير المدى (مثلاً 15 دقيقة) لتفادي تحميل الداتابيز في كل مرة يفتح الأدمن الصفحة
- فكّر مستقبلاً في جدول `daily_analytics_snapshots` يتحدّث يوميًا عبر Scheduled Job بدل حساب كل حاجة live، خصوصًا لما عدد المستخدمين يكبر

---

## 19. ملاحظات مهمة

- لا تستخدم `localStorage`/`sessionStorage` في أي مكون Vue — استخدم Pinia للـ state management
- كل الـ API responses لازم تكون بصيغة JSON موحدة: `{ success, data, message }`
- استخدم Form Requests للـ validation بدل ما تكتبها جوه الـ controller
- اتبع PSR-12 وLaravel conventions القياسية في التسمية

---

**ابدأ التنفيذ الآن خطوة بخطوة، واعرض لي نتيجة كل مرحلة رئيسية قبل الانتقال للمرحلة اللي بعدها.**
