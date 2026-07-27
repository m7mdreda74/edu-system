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
 * Teachers get a full public profile — headline, bio, intro video, experience —
 * because that is the surface students choose from, and a teacher without one
 * shows up as an empty card.
 */
class AccountsSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** A placeholder every teacher's intro video points at. */
    private const DEMO_VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

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

    /**
     * Teachers come from the faculty plan, which is keyed by subject — so a
     * teacher is created under exactly one, by construction.
     */
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

    /** @return array<int, User> */
    private function seedStudents(): array
    {
        $names = [
            'محمد رضا', 'نورة العلي', 'عبدالرحمن سعد', 'مريم الباكر', 'فهد المري',
            'دانة الكبيسي', 'سلطان الهاجري', 'شيخة النصر', 'علي الجابر', 'موزة السليطي',
            'حمد الرميحي', 'العنود المسند', 'ناصر الخليفي', 'لولوة البوعينين', 'جاسم الفضالة',
            'هند المالكي', 'راشد الخاطر', 'سارة المناعي', 'خليفة العبيدلي', 'أمل الأنصاري',
            'طلال المهندي', 'ريم الدرويش', 'صالح النعمة', 'غالية آل خليفة',
        ];

        // Spread across the whole ladder so every stage has someone in it.
        $gradeKeys = GradeLevel::where('is_active', true)->orderBy('id')->pluck('key')->all();
        $students  = [];

        foreach ($names as $index => $name) {
            $students[] = $this->makeUser('student', [
                'name'        => $name,
                'email'       => 'student' . ($index + 1) . '@altafawwuq.com',
                'phone'       => '+9745510' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'grade_level' => $gradeKeys[$index % count($gradeKeys)],
            ]);
        }

        // A memorable one for demos, in a grade that has plenty of teachers.
        $students[] = $this->makeUser('student', [
            'name'        => 'طالب تجريبي',
            'email'       => 'student@altafawwuq.com',
            'phone'       => '+97455000201',
            'grade_level' => 'grade_12_science',
        ]);

        return $students;
    }

    /** @param array<int, User> $students */
    private function seedParents(array $students): void
    {
        $parents = [
            ['name' => 'رضا محمود',   'email' => 'parent@altafawwuq.com',  'phone' => '+97455000301'],
            ['name' => 'أحمد العلي',  'email' => 'parent2@altafawwuq.com', 'phone' => '+97455000302'],
            ['name' => 'سعد الدوسري', 'email' => 'parent3@altafawwuq.com', 'phone' => '+97455000303'],
            ['name' => 'خالد الباكر', 'email' => 'parent4@altafawwuq.com', 'phone' => '+97455000304'],
        ];

        $relationships = ['father', 'mother', 'guardian'];

        foreach ($parents as $index => $definition) {
            $parent = $this->makeUser('parent', $definition);

            // Two children each, taken from the top of the student list so the
            // demo parent always has someone to look at.
            foreach ([$index * 2, $index * 2 + 1] as $position) {
                if (! isset($students[$position])) {
                    continue;
                }

                ParentStudentLink::firstOrCreate(
                    ['parent_user_id' => $parent->id, 'student_user_id' => $students[$position]->id],
                    ['relationship' => $relationships[$index % count($relationships)], 'verified_at' => now()],
                );
            }
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
