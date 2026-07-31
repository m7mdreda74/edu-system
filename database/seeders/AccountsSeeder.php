<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Admins, teachers, students and parents.
 *
 * Scale: 20 students per grade level + 1 parent per 2 students.
 * Teachers: 3 per subject (defined in TeachingStaff).
 */
class AccountsSeeder extends Seeder
{
    public const PASSWORD = 'password';

    private const DEMO_VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    /** Arabic male names */
    private const MALE_NAMES = [
        'محمد رضا', 'عبدالرحمن سعد', 'فهد المري', 'سلطان الهاجري', 'علي الجابر',
        'حمد الرميحي', 'ناصر الخليفي', 'جاسم الفضالة', 'راشد الخاطر', 'خليفة العبيدلي',
        'طلال المهندي', 'صالح النعمة', 'محمد الكواري', 'عبدالله آل ثاني', 'يوسف الهاجري',
        'فيصل المناعي', 'عمر الأنصاري', 'بدر الخليفي', 'سعيد المالكي', 'أحمد الدوسري',
        'خالد الغانم', 'عيسى الرميحي', 'عادل الشمري', 'نواف الكبيسي', 'وليد السليطي',
        'سلمان المسند', 'ثاني القحطاني', 'حسن البوعينين', 'بسام الحداد', 'إبراهيم الكعبي',
    ];

    /** Arabic female names */
    private const FEMALE_NAMES = [
        'نورة العلي', 'مريم الباكر', 'دانة الكبيسي', 'شيخة النصر', 'موزة السليطي',
        'العنود المسند', 'لولوة البوعينين', 'هند المالكي', 'سارة المناعي', 'أمل الأنصاري',
        'ريم الدرويش', 'غالية آل خليفة', 'لطيفة السويدي', 'رنا الهاجري', 'فاطمة النعيمي',
        'حصة الكواري', 'عائشة المسند', 'مريم الدوسري', 'منى العبيدلي', 'ليلى الرشيد',
        'خولة الجابر', 'مها الخاطر', 'نجلاء الشمري', 'شذى الفضالة', 'روان الرميحي',
        'هيا الكبيسي', 'ولاء المناعي', 'جواهر الدوسري', 'نبيلة الهاجري', 'إيمان القحطاني',
    ];

    /** Parent names */
    private const PARENT_NAMES = [
        ['name' => 'رضا محمود',    'gender' => 'father'],
        ['name' => 'أحمد العلي',   'gender' => 'father'],
        ['name' => 'سعد الدوسري',  'gender' => 'father'],
        ['name' => 'خالد الباكر',  'gender' => 'father'],
        ['name' => 'محمد المري',   'gender' => 'father'],
        ['name' => 'عبدالله الكواري', 'gender' => 'father'],
        ['name' => 'فارس الهاجري', 'gender' => 'father'],
        ['name' => 'ناصر الخليفي', 'gender' => 'father'],
        ['name' => 'سلطان المسند', 'gender' => 'father'],
        ['name' => 'حمد الأنصاري', 'gender' => 'father'],
        ['name' => 'فاطمة العلي',  'gender' => 'mother'],
        ['name' => 'مريم الخاطر',  'gender' => 'mother'],
        ['name' => 'نورة الدوسري', 'gender' => 'mother'],
        ['name' => 'هند الشمري',   'gender' => 'mother'],
        ['name' => 'شيخة الغانم',  'gender' => 'mother'],
        ['name' => 'موزة السليطي', 'gender' => 'mother'],
        ['name' => 'عائشة المنصوري', 'gender' => 'mother'],
        ['name' => 'لولوة الكبيسي', 'gender' => 'mother'],
        ['name' => 'دانة الفضالة', 'gender' => 'mother'],
        ['name' => 'العنود الرميحي', 'gender' => 'mother'],
    ];

    public function run(): void
    {
        foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->seedAdmins();
        $this->seedTeachers();
        $students = $this->seedStudents();
        $this->seedParents($students);
    }

    private function seedAdmins(): void
    {
        $this->makeUser('admin', [
            'name'  => 'مدير المنصة',
            'email' => 'admin@altafawwuq.com',
            'phone' => '+97455000001',
        ]);

        $this->makeUser('admin', [
            'name'  => 'مشرف المحتوى',
            'email' => 'supervisor@altafawwuq.com',
            'phone' => '+97455000002',
        ]);
    }

    private function seedTeachers(): void
    {
        foreach (TeachingStaff::teachers() as $definition) {
            $this->makeUser('teacher', [
                'name'                  => $definition['name'],
                'email'                 => $definition['email'],
                'phone'                 => $definition['phone'],
                'headline'              => $definition['headline'],
                'bio'                   => $definition['bio'],
                'years_experience'      => $definition['experience'],
                'is_featured'           => $definition['featured'] ?? false,
                'commission_percent'    => $definition['commission'],
                'intro_video_url'       => self::DEMO_VIDEO,
                'intro_video_thumbnail' => null,
            ]);
        }
    }

    /**
     * 20 students per grade level, spread evenly between male/female names.
     *
     * @return array<int, User>
     */
    private function seedStudents(): array
    {
        $grades    = GradeLevel::where('is_active', true)->orderBy('id')->get();
        $students  = [];
        $counter   = 1;

        $allNames = [];
        for ($i = 0; $i < 30; $i++) {
            $allNames[] = self::MALE_NAMES[$i];
            $allNames[] = self::FEMALE_NAMES[$i];
        }

        foreach ($grades as $gradeIndex => $grade) {
            for ($slot = 0; $slot < 20; $slot++) {
                $nameIndex  = ($gradeIndex * 20 + $slot) % count($allNames);
                $name       = $allNames[$nameIndex];
                $email      = 'student' . $counter . '@altafawwuq.com';
                $phone      = '+9745512' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);

                $students[] = $this->makeUser('student', [
                    'name'        => $name,
                    'email'       => $email,
                    'phone'       => $phone,
                    'grade_level' => $grade->key,
                ]);

                $counter++;
            }
        }

        // Memorable demo account in grade_12_science
        $students[] = $this->makeUser('student', [
            'name'        => 'طالب تجريبي',
            'email'       => 'student@altafawwuq.com',
            'phone'       => '+97455000201',
            'grade_level' => 'grade_12_science',
        ]);

        return $students;
    }

    /**
     * 1 parent per 2 students — each parent linked to exactly 2 children.
     *
     * @param  array<int, User>  $students
     */
    private function seedParents(array $students): void
    {
        $relationships = ['father', 'mother', 'guardian'];
        $parentDefs    = self::PARENT_NAMES;
        $parentIndex   = 0;
        $phoneCounter  = 1;

        // Chunk students into pairs, create a parent for each pair.
        $chunks = array_chunk($students, 2);

        foreach ($chunks as $chunkIndex => $pair) {
            $def    = $parentDefs[$parentIndex % count($parentDefs)];
            $email  = $parentIndex === 0
                ? 'parent@altafawwuq.com'
                : 'parent' . ($parentIndex + 1) . '@altafawwuq.com';

            $parent = $this->makeUser('parent', [
                'name'  => $def['name'],
                'email' => $email,
                'phone' => '+9745513' . str_pad((string) $phoneCounter, 4, '0', STR_PAD_LEFT),
            ]);

            $relationship = $def['gender'] === 'mother' ? 'mother' : 'father';

            foreach ($pair as $student) {
                ParentStudentLink::firstOrCreate(
                    ['parent_user_id' => $parent->id, 'student_user_id' => $student->id],
                    ['relationship' => $relationship, 'verified_at' => now()],
                );
            }

            $parentIndex++;
            $phoneCounter++;
        }
    }

    private function makeUser(string $role, array $attributes): User
    {
        $user = User::create([
            'password'          => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'is_active'         => true,
            ...$attributes,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
