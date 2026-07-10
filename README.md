# 🏆 منصة التفوق التعليمية | Al-Tafawwuq Educational Platform

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js" alt="Vue 3" />
  <img src="https://img.shields.io/badge/InertiaJS-1.x-9553E6?style=for-the-badge&logo=inertiajs" alt="InertiaJS" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css" alt="Tailwind CSS" />
</p>

---

## 📖 نبذة عن المشروع / Project Overview

**منصة التفوق** هي نظام إدارة تعليمي متكامل (LMS) وتفاعلي فخم مصمم بهوية عنابية قطرية فاخرة. تتيح المنصة للمعلمين والطلاب إدارة وتلقي المحتوى التعليمي والدروس المباشرة والاختبارات بسلاسة تامة، مع لوحة تحكم إدارية خارقة تُمكّن المسؤول من تخصيص وتعديل كل محتويات وعناصر المنصة والصفحات الرئيسية دون الحاجة للمس الأكواد البرمجية.

**Al-Tafawwuq** is a premium, interactive Learning Management System (LMS) built with a luxurious Qatari Maroon identity. It offers students and teachers a seamless environment for courses, live lectures, and quizzes. It includes a powerful administrative control panel that enables total dynamic page configuration without touching a line of code.

---

## ✨ المميزات الرئيسية / Key Features

### 🛠️ لوحة تحكم خارقة ومحرر الصفحات التفاعلي (Super Admin Page Editor)
* **تعديل حي وتام لكل صفحات المنصة:** التحكم المطلق في النصوص والروابط والأزرار وقسم الهيرو (Hero Sections) والمعلومات الترحيبية وتنزيل التطبيقات.
* **إدارة النوافذ التنبيهية (Welcome Popups):** إمكانية تفعيل نافذة ترحيبية عامة للطلاب تحتوي على فيديو تعريفي أو إرشادات مع تحكم كامل بالظهور.
* **شريط الحفظ العائم والذكي:** شريط حفظ تفاعلي زجاجي شفاف يسهل عملية حفظ التغييرات أثناء التعديل.

### 🌓 الثيم الذكي والسايدبار المتجاوب (Dynamic Light/Dark Modes)
* **دعم كامل للوضعين الداكن والفاتح** لراحة عين المستخدم مع ألوان مخصصة ومريحة.
* **سايدبار متجاوب كلياً (Dynamic Sidebar):** يتحول تلقائياً إلى اللون الأبيض الأنيق في المظهر الفاتح وإلى الأزرق الداكن الفخم في المظهر المظلم مع تأثيرات هوفر (Hover) واضحة.

### 📚 إدارة المحتوى التعليمي (Educational Content Management)
* **الكورسات والمواد:** تصنيف مرن للمواد الدراسية، والفصول، والكورسات مع نظام الكوبونات وخصومات الاشتراكات.
* **نظام الحصص والدروس المباشرة (Live Sessions):** دعم غرف البث والدروس التفاعلية المباشرة عبر الإنترنت.
* **الاختبارات التفاعلية (Interactive Quizzes):** بنك أسئلة واختبارات لتقييم مستويات الطلاب مع إظهار النتائج والتقارير.

### 🔍 نظام نتائج الطلاب (Student Results Portal)
* محرك بحث سريع للنتائج يتيح للطلاب وأولياء الأمور الاستعلام الفوري عن الدرجات والشهادات بموجب رقم الجلوس أو الرقم القومي.

---

## 🛠️ التقنيات المستخدمة / Tech Stack

* **Back-End:** [Laravel 11](https://laravel.com)
* **Front-End:** [Vue 3](https://vuejs.org) (Composition API) & [InertiaJS](https://inertiajs.com)
* **Styles:** [Tailwind CSS 3](https://tailwindcss.com) (Frosted glassmorphism, responsive elements)
* **Database:** MySQL / PostgreSQL
* **Tooling:** Vite, NPM

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
