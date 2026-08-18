<!doctype html>
<html lang="ar" dir="rtl">
<body style="font-family: Arial, sans-serif; line-height: 1.8; color: #23151a;">
    <h2>رسالة جديدة من صفحة تواصل معنا</h2>

    <p><strong>الاسم:</strong> {{ $contact['name'] }}</p>
    <p><strong>البريد الإلكتروني:</strong> {{ $contact['email'] ?: 'غير مذكور' }}</p>
    <p><strong>رقم الهاتف:</strong> {{ $contact['phone'] }}</p>

    <hr>

    <p><strong>الرسالة:</strong></p>
    <div style="white-space: pre-wrap; background: #f8f5f1; padding: 16px; border-radius: 8px;">
        {{ $contact['message'] }}
    </div>
</body>
</html>
