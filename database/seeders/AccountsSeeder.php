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

    /** @return array<int, array<string, mixed>> */
    public static function teacherDefinitions(): array
    {
        return [
            [
                'email'            => 'ahmed@altafawwuq.com',
                'name'             => 'أ. أحمد الكواري',
                'phone'            => '+97455000101',
                'headline'         => 'معلم رياضيات للثانوية — 14 سنة خبرة',
                'bio'              => 'أدرّس الرياضيات من الأساس: كل قاعدة تبدأ بسؤال من امتحان وزاري حقيقي، ثم نبنيها خطوة خطوة حتى يصبح الحل بديهياً. أركّز على التفاضل والتكامل والهندسة التحليلية للصفين الحادي عشر والثاني عشر.',
                'years_experience' => 14,
                'is_featured'      => true,
                'commission'       => 18,
            ],
            [
                'email'            => 'sara@altafawwuq.com',
                'name'             => 'أ. سارة المهندي',
                'phone'            => '+97455000102',
                'headline'         => 'معلمة فيزياء — الشرح بالتجربة',
                'bio'              => 'كل درس يبدأ بتجربة أو موقف من الحياة اليومية قبل أي قانون. الطالب يفهم لماذا قبل أن يحفظ كيف، وهذا ما يجعل المعلومة تثبت حتى يوم الامتحان.',
                'years_experience' => 9,
                'is_featured'      => true,
                'commission'       => 20,
            ],
            [
                'email'            => 'khaled@altafawwuq.com',
                'name'             => 'أ. خالد آل ثاني',
                'phone'            => '+97455000103',
                'headline'         => 'معلم أحياء — خرائط ذهنية وملخصات',
                'bio'              => 'أسلوبي يعتمد على الخرائط الذهنية التي تختصر الوحدة كاملة في صفحة واحدة، مع مراجعات مركّزة قبل الامتحانات مباشرة.',
                'years_experience' => 7,
                'commission'       => 20,
            ],
            [
                'email'            => 'noura@altafawwuq.com',
                'name'             => 'أ. نورة العطية',
                'phone'            => '+97455000104',
                'headline'         => 'معلمة لغة عربية — من الابتدائي إلى الثانوي',
                'bio'              => 'النحو ليس قواعد تُحفظ بل منطق يُفهم. أدرّس العربية بأسلوب يربط القاعدة بالنص الأدبي، ويعالج ضعف الإملاء والتعبير من جذوره.',
                'years_experience' => 16,
                'is_featured'      => true,
                'commission'       => 18,
            ],
            [
                'email'            => 'yousef@altafawwuq.com',
                'name'             => 'أ. يوسف الحداد',
                'phone'            => '+97455000105',
                'headline'         => 'معلم لغة إنجليزية — قواعد ومحادثة',
                'bio'              => 'أجمع بين إتقان القواعد المطلوبة في المنهج وبين بناء ثقة الطالب في التحدث، مع تدريب مكثف على الكتابة ومهارات الامتحان.',
                'years_experience' => 11,
                'commission'       => 20,
            ],
            [
                'email'            => 'maryam@altafawwuq.com',
                'name'             => 'أ. مريم الدوسري',
                'phone'            => '+97455000106',
                'headline'         => 'معلمة تاريخ — المسار الأدبي',
                'bio'              => 'أحوّل التاريخ من تواريخ تُحفظ إلى قصة مترابطة تفهم أسبابها ونتائجها. متخصصة في منهج المسار الأدبي للحادي عشر والثاني عشر.',
                'years_experience' => 10,
                'is_featured'      => true,
                'commission'       => 20,
            ],
            [
                'email'            => 'jassim@altafawwuq.com',
                'name'             => 'أ. جاسم البوعينين',
                'phone'            => '+97455000109',
                'headline'         => 'معلم علوم حاسب — المسار التكنولوجي',
                'bio'              => 'أدرّس البرمجة وقواعد البيانات وتصميم الشبكات لطلاب المسار التكنولوجي، بمشاريع عملية يبنيها الطالب بنفسه بدل الحفظ.',
                'years_experience' => 9,
                'is_featured'      => true,
                'commission'       => 20,
            ],
            [
                'email'            => 'hessa@altafawwuq.com',
                'name'             => 'أ. حصة الكواري',
                'phone'            => '+97455000110',
                'headline'         => 'معلمة كيمياء — من العاشر إلى الثاني عشر',
                'bio'              => 'أربط كل تفاعل بمثال من الحياة أو الصناعة، فيصبح الحفظ نتيجة للفهم لا بديلاً عنه.',
                'years_experience' => 10,
                'commission'       => 20,
            ],
            [
                'email'            => 'salem@altafawwuq.com',
                'name'             => 'أ. سالم المري',
                'phone'            => '+97455000111',
                'headline'         => 'معلم علوم — الابتدائي والإعدادي',
                'bio'              => 'العلوم في هذه المرحلة تُبنى بالتجربة والسؤال، لا بالتلقين. حصصي قائمة على تجارب بسيطة يعيدها الطالب في البيت.',
                'years_experience' => 13,
                'is_featured'      => true,
                'commission'       => 20,
            ],
            [
                'email'            => 'latifa@altafawwuq.com',
                'name'             => 'أ. لطيفة السويدي',
                'phone'            => '+97455000112',
                'headline'         => 'معلمة جغرافيا — المسار الأدبي',
                'bio'              => 'الجغرافيا خرائط تُقرأ لا أسماء تُحفظ. أدرّب الطالب على تحليل الخريطة والرسم البياني قبل أي شيء.',
                'years_experience' => 8,
                'commission'       => 20,
            ],
            [
                'email'            => 'rashid@altafawwuq.com',
                'name'             => 'أ. راشد الخاطر',
                'phone'            => '+97455000113',
                'headline'         => 'معلم تكنولوجيا المعلومات',
                'bio'              => 'من أساسيات الحاسب حتى بناء موقع كامل — كل حصة ينتج فيها الطالب شيئاً يشغّله بنفسه.',
                'years_experience' => 7,
                'commission'       => 20,
            ],
            [
                'email'            => 'abdullah@altafawwuq.com',
                'name'             => 'أ. عبدالله الشمري',
                'phone'            => '+97455000107',
                'headline'         => 'معلم تربية إسلامية — الابتدائي والإعدادي',
                'bio'              => 'أدرّس التربية الإسلامية بأسلوب قصصي مبسّط يناسب الأعمار الصغيرة، مع تركيز على التجويد وحفظ جزء عمّ.',
                'years_experience' => 8,
                'commission'       => 22,
            ],
            [
                'email'            => 'fatima@altafawwuq.com',
                'name'             => 'أ. فاطمة النعيمي',
                'phone'            => '+97455000108',
                'headline'         => 'معلمة رياضيات وعلوم — المرحلة الابتدائية',
                'bio'              => 'أبني الأساس الصحيح للطفل في الحساب والعلوم عبر الأنشطة والألعاب التعليمية، لأن ضعف الابتدائي يلاحق الطالب لسنوات.',
                'years_experience' => 12,
                'commission'       => 22,
            ],
        ];
    }

    private function seedTeachers(): void
    {
        foreach (self::teacherDefinitions() as $definition) {
            $this->makeUser('teacher', [
                'name'                  => $definition['name'],
                'email'                 => $definition['email'],
                'phone'                 => $definition['phone'],
                'headline'              => $definition['headline'],
                'bio'                   => $definition['bio'],
                'years_experience'      => $definition['years_experience'],
                'is_featured'           => $definition['is_featured'] ?? false,
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
