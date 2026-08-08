# خطة تطبيق الموبايل — منصة التفوق (AlTafawwuq Mobile App)

> **تاريخ الإنشاء:** 2026-08-08  
> **الحالة:** قيد المراجعة — لم يُبدأ التنفيذ بعد

---

## نظرة عامة

المشروع الحالي هو منصة تعليمية Laravel + Inertia.js + Vue.js مكتملة البناء. الهدف هو إضافة **تطبيق موبايل native** بأربعة موديولز مستقلة يتواصلون مع **REST API جديد** يُبنى فوق نفس الـ Business Logic الموجود (Services & Repositories) بدون أي تعديل عليها.

> **ملاحظة مهمة:** الـ Business Logic (Application Layer + Domain Layer) موجود بالفعل ومش هنلمسه. هنبني فقط:
> 1. **API Layer جديد** في Laravel (Controllers + Resources + Routes)
> 2. **تطبيق موبايل Flutter**

---

## الـ Tech Stack المقترح

### تطبيق الموبايل

| الاختيار | السبب |
|---|---|
| **Flutter** | كود واحد → Android + iOS، أداء native، دعم ممتاز لـ RTL العربي، ecosystem غني |
| **Riverpod** | State Management موثوق وقابل للاختبار |
| **Dio** | HTTP Client مع Interceptors للـ Auth token |
| **go_router** | Routing مرن مع Guards لحماية الصفحات |
| **flutter_secure_storage** | تخزين Token بأمان |
| **firebase_messaging** | Push Notifications |
| **cached_network_image** | تحميل ذكي للصور |
| **video_player / better_player** | تشغيل الفيديو مع حماية |

### الـ API (Laravel — نفس المشروع)

| الاختيار | السبب |
|---|---|
| **Laravel Sanctum** | موجود بالفعل في المشروع، مناسب للـ Mobile Tokens |
| **Laravel API Resources** | تحويل Models لـ JSON موحد |
| **Spatie Permission** | موجود، نفس الأدوار الحالية + `parent` role جديد |

---

## موديولز التطبيق الأربعة

```
┌─────────────────────────────────────────────────────────────┐
│                  AlTafawwuq Mobile App                      │
│                                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │  Admin   │  │ Teacher  │  │ Student  │  │  Parent  │  │
│  │  Module  │  │  Module  │  │  Module  │  │  Module  │  │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘  │
│                        ↕ REST API ↕                         │
│              ┌────────────────────────────┐                 │
│              │   Laravel API Layer (NEW)   │                 │
│              │  /api/v1/mobile/*           │                 │
│              └────────────────────────────┘                 │
│                        ↕                                    │
│              ┌────────────────────────────┐                 │
│              │  Application Services      │                 │
│              │  (موجودة — لا تُعدَّل)      │                 │
│              └────────────────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

---

## الجزء الأول: Laravel REST API

### أ. بنية الـ Routes

```
/api/v1/
├── auth/
│   ├── POST   login
│   ├── POST   register
│   ├── POST   logout
│   ├── POST   forgot-password
│   └── GET    me
│
├── [admin]/
│   ├── dashboard/stats
│   ├── users/                   (CRUD)
│   ├── teaching-groups/         (الموافقة/الرفض)
│   ├── payments/
│   ├── teacher-payouts/
│   ├── analytics/
│   └── settings/
│
├── [teacher]/
│   ├── dashboard/stats
│   ├── teaching-groups/         (CRUD كامل)
│   ├── lessons/                 (CRUD + رفع فيديو)
│   ├── live-sessions/           (جدولة + إدارة)
│   ├── students/                (قائمة طلابه)
│   ├── worksheets/              (CRUD)
│   ├── quizzes/                 (CRUD)
│   ├── payouts/
│   └── attendance-reports/
│
├── [student]/
│   ├── dashboard/
│   ├── explore/                 (browse teaching groups)
│   ├── my-subscriptions/
│   ├── lessons/{id}/
│   ├── lesson-progress/
│   ├── live-sessions/
│   ├── quizzes/{id}/attempt
│   ├── worksheets/
│   └── certificates/
│
├── [parent]/
│   ├── dashboard/
│   ├── children/                (ربط/عرض الأبناء)
│   ├── children/{id}/progress
│   ├── children/{id}/attendance
│   ├── children/{id}/quiz-results
│   └── children/{id}/payments
│
└── [shared]/
    ├── notifications/
    ├── conversations/           (الشات)
    ├── profile/
    └── subjects/
```

### ب. API Response Format الموحد

```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": { },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

### ج. الملفات الجديدة في Laravel

#### API Controllers (جديدة بالكامل)

```
app/Http/Controllers/Api/V1/
├── Auth/
│   └── MobileAuthController.php
├── Admin/
│   ├── AdminDashboardController.php
│   ├── AdminUsersController.php
│   ├── AdminTeachingGroupsController.php
│   ├── AdminPaymentsController.php
│   ├── AdminPayoutsController.php
│   └── AdminAnalyticsController.php
├── Teacher/
│   ├── TeacherDashboardController.php
│   ├── TeacherGroupsController.php
│   ├── TeacherLessonsController.php
│   ├── TeacherLiveSessionsController.php
│   ├── TeacherStudentsController.php
│   ├── TeacherWorksheetsController.php
│   ├── TeacherQuizzesController.php
│   └── TeacherAttendanceController.php
├── Student/
│   ├── StudentDashboardController.php
│   ├── StudentExploreController.php
│   ├── StudentSubscriptionsController.php
│   ├── StudentLessonsController.php
│   ├── StudentLiveSessionsController.php
│   ├── StudentQuizController.php
│   ├── StudentWorksheetsController.php
│   └── StudentCertificatesController.php
├── Parent/
│   ├── ParentDashboardController.php
│   ├── ParentChildrenController.php
│   └── ParentReportsController.php
└── Shared/
    ├── NotificationsController.php
    ├── ConversationsController.php
    └── ProfileController.php
```

#### API Resources (JSON Transformers)

```
app/Http/Resources/Api/
├── UserResource.php
├── TeachingGroupResource.php
├── LessonResource.php
├── LiveSessionResource.php
├── SubscriptionResource.php
├── QuizResource.php
├── WorksheetResource.php
├── NotificationResource.php
├── ConversationResource.php
├── PaymentResource.php
└── ChildProgressResource.php
```

#### التعديلات على الملفات الموجودة

| الملف | نوع التعديل | الوصف |
|---|---|---|
| `routes/api.php` | تعديل | إضافة prefix `/v1` مع grouping حسب الرول |
| `app/Http/Middleware/` | جديد | `EnsureMobileRole.php` للتحقق من الرول |
| `database/migrations/` | جديد | إضافة `fcm_token` لجدول users |
| `database/migrations/` | جديد | إضافة `device_type` في user_sessions |

---

## الجزء الثاني: Flutter Mobile App

### بنية مجلدات المشروع

```
mobile/                              ← مجلد مستقل جنب edu-system
├── lib/
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart      (Dio setup + interceptors)
│   │   │   ├── api_endpoints.dart   (ثوابت الـ endpoints)
│   │   │   └── api_response.dart    (Generic response wrapper)
│   │   ├── auth/
│   │   │   ├── auth_provider.dart
│   │   │   └── auth_repository.dart
│   │   ├── models/                  (Shared models)
│   │   ├── widgets/                 (Shared UI components)
│   │   ├── theme/
│   │   │   ├── app_theme.dart       (ألوان + fonts Cairo/Inter)
│   │   │   └── app_colors.dart
│   │   └── utils/
│   │       ├── rtl_helper.dart
│   │       └── date_formatter.dart
│   │
│   ├── features/
│   │   ├── auth/                    (Login, Register, Forgot Password)
│   │   │
│   │   ├── admin/
│   │   │   ├── dashboard/
│   │   │   ├── users/
│   │   │   ├── groups/
│   │   │   ├── payments/
│   │   │   └── analytics/
│   │   │
│   │   ├── teacher/
│   │   │   ├── dashboard/
│   │   │   ├── groups/
│   │   │   ├── lessons/
│   │   │   ├── live_sessions/
│   │   │   ├── students/
│   │   │   ├── worksheets/
│   │   │   ├── quizzes/
│   │   │   └── earnings/
│   │   │
│   │   ├── student/
│   │   │   ├── dashboard/
│   │   │   ├── explore/
│   │   │   ├── my_subscriptions/
│   │   │   ├── lesson_player/
│   │   │   ├── live_sessions/
│   │   │   ├── quiz/
│   │   │   ├── worksheets/
│   │   │   └── certificates/
│   │   │
│   │   └── parent/
│   │       ├── dashboard/
│   │       ├── children/
│   │       ├── child_progress/
│   │       ├── child_attendance/
│   │       └── child_payments/
│   │
│   ├── shared/
│   │   ├── notifications/
│   │   ├── chat/
│   │   └── profile/
│   │
│   └── main.dart
│
├── pubspec.yaml
├── android/
└── ios/
```

---

## تفصيل شاشات كل موديول

### 🔴 Admin Module

| الشاشة | الوصف |
|---|---|
| Dashboard | إحصائيات: إيرادات، مستخدمين، مجموعات نشطة — مع Charts |
| إدارة المستخدمين | قائمة + بحث + تفعيل/تعطيل + تغيير رول |
| إدارة المجموعات التعليمية | الموافقة على مجموعات المدرسين، تعديل، حذف |
| المدفوعات | قائمة كل المدفوعات، فلاتر بالحالة والتاريخ |
| مستحقات المدرسين | عرض وصرف المستحقات الشهرية |
| التقارير والتحليلات | Charts للإيرادات، أكثر المجموعات مبيعاً، معدل الإكمال |
| الإعدادات | نسبة العمولة، بوابات الدفع، الإعدادات العامة |

---

### 🟡 Teacher Module

| الشاشة | الوصف |
|---|---|
| Dashboard | طلابي، أرباحي، آخر التقييمات، الحصص القادمة |
| مجموعاتي | إنشاء/تعديل المجموعات التعليمية |
| الدروس | إضافة/تعديل دروس + رفع فيديو |
| الحصص المباشرة | جدولة + إدارة الجلسة Live (Agora SDK) |
| طلابي | قائمة طلاب كل مجموعة + تقدم كل طالب |
| الشيتات | رفع شيتات + مراجعة التسليمات + تقييم |
| الكويزات | إنشاء وإدارة الاختبارات |
| تقرير الحضور | جدول حضور الطلاب في الحصص المباشرة |
| الأرباح والمستحقات | تفاصيل الأرباح الشهرية |

---

### 🟢 Student Module

| الشاشة | الوصف |
|---|---|
| Dashboard | كورساتي، تقدمي، الحصص القادمة، آخر النشاط |
| استكشاف | تصفح المجموعات التعليمية مع فلاتر + بحث |
| تفاصيل مجموعة | معلومات المجموعة + الدروس + زر اشتراك |
| مشغّل الفيديو | تشغيل الدرس مع تتبع التقدم (Signed URL) |
| الحصص المباشرة | الدخول للحصة عبر Agora |
| الاختبارات | حل الكويز مع Timer Server-Side + حماية الغش |
| الشيتات | تحميل + تسليم الواجبات |
| شهاداتي | عرض وتحميل الشهادات |

---

### 🔵 Parent Module

| الشاشة | الوصف |
|---|---|
| Dashboard | ملخص أداء كل الأبناء |
| ربط ابن جديد | إدخال كود الدعوة أو رقم الطالب للربط |
| تفاصيل الابن | اختيار من قائمة الأبناء |
| تقدم الابن | نسبة إكمال كل مجموعة + آخر نشاط |
| نتائج الاختبارات | كل الكويزات ونتائجها |
| سجل الحضور | حضور الحصص المباشرة |
| المدفوعات | الفواتير والاشتراكات (View-Only) |

> ⚠️ **تحذير أمني:** ولي الأمر **قراءة فقط** في كل الـ API — أي Endpoint كتابي يتحقق من Policy ويرفض `parent` role.

---

## الـ Authentication Flow

```
     Login Screen (email + password)
              ↓
     POST /api/v1/auth/login
              ↓
     Laravel Sanctum → token + role
              ↓
  ┌───────────┬───────────┬───────────┐
  ↓           ↓           ↓           ↓
Admin       Teacher     Student     Parent
Shell       Shell       Shell       Shell
```

- **Token** يُخزن في `flutter_secure_storage`
- **Role** يحدد الـ Shell (Bottom Navigation) اللي هيظهر
- **Token Refresh** تلقائي عبر Dio Interceptor

---

## نظام الإشعارات

```
Laravel Queue Job → Firebase FCM → Mobile App
```

| الحدث | المستقبل | القناة |
|---|---|---|
| حصة مباشرة بعد 15 دقيقة | طالب | Push + In-App |
| اكتمال الدفع | طالب | Push + In-App |
| كورس جديد للمراجعة | أدمن | Push + In-App |
| موافقة على كورسك | مدرس | Push + In-App |
| تصحيح واجب | طالب | Push + In-App |
| ملخص أسبوعي للأداء | ولي أمر | Push + In-App |

---

## قاعدة البيانات — التعديلات المطلوبة

> معظم الجداول موجودة بالفعل. نحتاج فقط:

| التعديل | الملف |
|---|---|
| إضافة `fcm_token` لجدول `users` | Migration جديد |
| إضافة `parent` كـ Spatie Role (إن لم يكن موجوداً) | Seeder |
| إضافة `device_type` في `user_sessions` | Migration جديد |

---

## خطة التنفيذ المرحلية — 10 مراحل

| المرحلة | الوصف | المخرج |
|---|---|---|
| **1** | API Foundation | Auth endpoints + Base Controller + Resources |
| **2** | Admin API | كل Admin Controllers متصلة بالـ Services |
| **3** | Teacher API | كل Teacher Controllers |
| **4** | Student API | Student Controllers + Signed Video URLs |
| **5** | Parent API | Parent Controllers (View-Only مطلق) |
| **6** | Push Notifications | دمج Firebase FCM في Laravel |
| **7** | Flutter App Scaffold | إنشاء المشروع + core/ + Routing |
| **8** | UI Modules | شاشات كل موديول (Admin, Teacher, Student, Parent) |
| **9** | Features متقدمة | Video Player + Agora + Quiz Anti-cheat + Chat |
| **10** | Polish & Testing | RTL كامل + Dark Mode + Android/iOS Testing |

---

## أسئلة مفتوحة قبل البدء في التنفيذ

### ❓ السؤال 1: App Store
هل التطبيق هيُنشر على **Google Play** و **App Store**؟  
ده بيحدد إعداد Signing + Bundle ID + App ID من البداية.

### ❓ السؤال 2: بوابة الدفع في الموبايل
هل الشراء هيتم **من داخل التطبيق** (In-App Purchase)؟ أم **يفتح الـ Browser** لصفحة الدفع الموجودة؟  
> Apple بتاخد **30%** على أي In-App Purchase — ده قرار تجاري مهم.

### ❓ السؤال 3: الحصص المباشرة (Agora)
Agora مذكور في `.env` لكن بدون App ID — هل:
- مشترك فعلاً وعنده credentials؟
- أم هنستخدم الـ **WebRTC self-hosted** الموجود في المشروع بالفعل؟

### ❓ السؤال 4: الـ Design
هل في **Figma Design** جاهز للتطبيق؟  
أم هنصمم من صفر بنفس ألوان المنصة الحالية (أزرق/تركوازي + خط Cairo)؟
