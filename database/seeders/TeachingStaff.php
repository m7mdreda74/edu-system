<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * The full demo faculty — 3 teachers per subject per grade band.
 *
 * Prices are in the smallest currency unit (QAR × 100):
 *   60_000 = 600 QAR / month.
 */
final class TeachingStaff
{
    /** Days of the week, Sunday first (Carbon::dayOfWeek). */
    public const SUN = 0;
    public const MON = 1;
    public const TUE = 2;
    public const WED = 3;
    public const THU = 4;
    public const SAT = 6;

    private const SECONDARY_SCIENCE = ['grade_12_science', 'grade_11_science', 'grade_10'];
    private const SECONDARY_ARTS    = ['grade_12_arts', 'grade_11_arts'];
    private const SECONDARY_TECH    = ['grade_12_technology', 'grade_11_technology'];
    private const PREPARATORY       = ['grade_9', 'grade_8', 'grade_7'];
    private const PRIMARY_UPPER     = ['grade_6', 'grade_5', 'grade_4'];
    private const PRIMARY_LOWER     = ['grade_3', 'grade_2', 'grade_1'];

    /**
     * subject name => the teachers who teach it (3 per subject).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function plan(): array
    {
        return [

            // ─── الرياضيات ─────────────────────────────────────────────────
            'الرياضيات' => [
                [
                    'email' => 'ahmed@altafawwuq.com', 'name' => 'أ. أحمد الكواري',
                    'experience' => 14, 'featured' => true, 'commission' => 18,
                    'headline' => 'معلم رياضيات للثانوية — 14 سنة خبرة',
                    'bio' => 'أدرّس الرياضيات من الأساس: كل قاعدة تبدأ بسؤال من امتحان وزاري حقيقي، ثم نبنيها خطوة خطوة حتى يصبح الحل بديهياً. أركّز على التفاضل والتكامل والهندسة التحليلية.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 120_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 60_000, 'capacity' => 18, 'days' => [[self::SUN, '16:00', '17:30'], [self::TUE, '16:00', '17:30']]],
                        ['name' => 'مجموعة السبت المكثفة',   'price' => 75_000, 'capacity' => 12, 'days' => [[self::SAT, '09:00', '11:30']]],
                    ],
                ],
                [
                    'email' => 'mona@altafawwuq.com', 'name' => 'أ. منى العبيدلي',
                    'experience' => 11, 'commission' => 20,
                    'headline' => 'معلمة رياضيات — الإعدادي والمسار الأدبي',
                    'bio' => 'كثير من الطلاب يكرهون الرياضيات لأنهم فاتتهم خطوة في الإعدادي. أبدأ من حيث توقف الطالب فعلاً، لا من حيث يفترض المنهج.',
                    'grades' => [...self::PREPARATORY, ...self::SECONDARY_ARTS], 'private' => 80_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين والأربعاء', 'price' => 42_000, 'capacity' => 22, 'days' => [[self::MON, '18:00', '19:30'], [self::WED, '18:00', '19:30']]],
                        ['name' => 'مجموعة الخميس المسائية',  'price' => 48_000, 'capacity' => 16, 'days' => [[self::THU, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'fatima@altafawwuq.com', 'name' => 'أ. فاطمة النعيمي',
                    'experience' => 12, 'commission' => 22,
                    'headline' => 'معلمة رياضيات — المرحلة الابتدائية',
                    'bio' => 'أبني الأساس الصحيح للطفل في الحساب عبر الأنشطة والألعاب التعليمية، لأن ضعف الابتدائي يلاحق الطالب لسنوات.',
                    'grades' => [...self::PRIMARY_UPPER, ...self::PRIMARY_LOWER], 'private' => 50_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء',  'price' => 28_000, 'capacity' => 25, 'days' => [[self::SUN, '15:00', '16:00'], [self::TUE, '15:00', '16:00']]],
                        ['name' => 'مجموعة الاثنين المكثفة', 'price' => 32_000, 'capacity' => 20, 'days' => [[self::MON, '15:30', '17:00']]],
                    ],
                ],
            ],

            // ─── الفيزياء ──────────────────────────────────────────────────
            'الفيزياء' => [
                [
                    'email' => 'sara@altafawwuq.com', 'name' => 'أ. سارة المهندي',
                    'experience' => 9, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلمة فيزياء — الشرح بالتجربة',
                    'bio' => 'كل درس يبدأ بتجربة أو موقف من الحياة اليومية قبل أي قانون. الطالب يفهم لماذا قبل أن يحفظ كيف.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_TECH], 'private' => 125_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء',        'price' => 65_000, 'capacity' => 16, 'days' => [[self::WED, '17:00', '18:30']]],
                        ['name' => 'مجموعة الخميس المسائية', 'price' => 65_000, 'capacity' => 16, 'days' => [[self::THU, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'tariq@altafawwuq.com', 'name' => 'أ. طارق الأنصاري',
                    'experience' => 15, 'commission' => 18,
                    'headline' => 'معلم فيزياء — حل المسائل الوزارية',
                    'bio' => 'تركيزي كله على المسألة: كيف تقرأها، وكيف تختار القانون، وكيف تتفادى الأخطاء التي تتكرر كل عام في التصحيح.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 130_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت',          'price' => 70_000, 'capacity' => 14, 'days' => [[self::SAT, '17:00', '19:00']]],
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 68_000, 'capacity' => 16, 'days' => [[self::SUN, '18:00', '19:30'], [self::TUE, '18:00', '19:30']]],
                    ],
                ],
                [
                    'email' => 'walid@altafawwuq.com', 'name' => 'أ. وليد السليطي',
                    'experience' => 7, 'commission' => 22,
                    'headline' => 'معلم فيزياء — التقنية والميكانيكا',
                    'bio' => 'أركّز على مسائل الميكانيكا والديناميكا التي يجد فيها طلاب الثانوية صعوبة، مع شرح التحليل الرياضي المطلوب.',
                    'grades' => [...self::SECONDARY_TECH, ...self::PREPARATORY], 'private' => 90_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء والجمعة', 'price' => 48_000, 'capacity' => 20, 'days' => [[self::WED, '16:00', '17:30']]],
                    ],
                ],
            ],

            // ─── الكيمياء ──────────────────────────────────────────────────
            'الكيمياء' => [
                [
                    'email' => 'hessa@altafawwuq.com', 'name' => 'أ. حصة الكواري',
                    'experience' => 10, 'commission' => 20,
                    'headline' => 'معلمة كيمياء — من العاشر إلى الثاني عشر',
                    'bio' => 'أربط كل تفاعل بمثال من الحياة أو الصناعة، فيصبح الحفظ نتيجة للفهم لا بديلاً عنه.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 110_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين',          'price' => 55_000, 'capacity' => 20, 'days' => [[self::MON, '16:30', '18:00']]],
                        ['name' => 'مجموعة الثلاثاء والخميس', 'price' => 60_000, 'capacity' => 16, 'days' => [[self::TUE, '17:00', '18:30'], [self::THU, '17:00', '18:30']]],
                    ],
                ],
                [
                    'email' => 'badr@altafawwuq.com', 'name' => 'أ. بدر الهاجري',
                    'experience' => 6, 'commission' => 22,
                    'headline' => 'معلم كيمياء — الكيمياء العضوية خطوة بخطوة',
                    'bio' => 'العضوية ليست حفظاً لسلاسل، بل منطق يتكرر. أعطي الطالب المنطق مرة واحدة فيحل ما لم يره من قبل.',
                    'grades' => ['grade_12_science', 'grade_11_science'], 'private' => 105_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس',  'price' => 52_000, 'capacity' => 18, 'days' => [[self::THU, '16:00', '17:30']]],
                        ['name' => 'مجموعة السبت',    'price' => 55_000, 'capacity' => 14, 'days' => [[self::SAT, '11:00', '13:00']]],
                    ],
                ],
                [
                    'email' => 'mariam_k@altafawwuq.com', 'name' => 'أ. مريم الكعبي',
                    'experience' => 8, 'commission' => 20,
                    'headline' => 'معلمة كيمياء — الكيمياء الحسابية والتحليلية',
                    'bio' => 'أركّز على الحسابات الكيميائية والمسائل الكمية التي يضعف فيها الطلاب عادةً، مع ربطها بالأسئلة الوزارية.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_TECH], 'private' => 100_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 50_000, 'capacity' => 20, 'days' => [[self::SUN, '17:00', '18:30'], [self::TUE, '17:00', '18:30']]],
                    ],
                ],
            ],

            // ─── الأحياء ───────────────────────────────────────────────────
            'الأحياء' => [
                [
                    'email' => 'khaled@altafawwuq.com', 'name' => 'أ. خالد آل ثاني',
                    'experience' => 7, 'commission' => 20,
                    'headline' => 'معلم أحياء — خرائط ذهنية وملخصات',
                    'bio' => 'أسلوبي يعتمد على الخرائط الذهنية التي تختصر الوحدة كاملة في صفحة واحدة، مع مراجعات مركّزة قبل الامتحانات مباشرة.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 100_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء',         'price' => 52_000, 'capacity' => 20, 'days' => [[self::TUE, '19:00', '20:30']]],
                        ['name' => 'مجموعة السبت المكثفة',    'price' => 60_000, 'capacity' => 14, 'days' => [[self::SAT, '10:00', '12:30']]],
                    ],
                ],
                [
                    'email' => 'aisha@altafawwuq.com', 'name' => 'أ. عائشة المسند',
                    'experience' => 13, 'featured' => true, 'commission' => 18,
                    'headline' => 'معلمة أحياء — للراغبين في كليات الطب',
                    'bio' => 'أدرّس الأحياء بعمق يتجاوز المنهج قليلاً لمن ينوي الطب، مع تدريب على أسئلة التفكير لا الاسترجاع.',
                    'grades' => ['grade_12_science', 'grade_11_science'], 'private' => 115_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد',  'price' => 58_000, 'capacity' => 16, 'days' => [[self::SUN, '19:00', '20:30']]],
                        ['name' => 'مجموعة الأربعاء', 'price' => 58_000, 'capacity' => 14, 'days' => [[self::WED, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'sultan@altafawwuq.com', 'name' => 'أ. سلطان الغانم',
                    'experience' => 5, 'commission' => 22,
                    'headline' => 'معلم أحياء — علم الوراثة والتطور',
                    'bio' => 'أبسّط وحدتي الوراثة والتطور اللتين يجد فيهما الطلاب صعوبة، من خلال رسومات توضيحية وجداول مقارنة.',
                    'grades' => [...self::SECONDARY_SCIENCE, 'grade_10'], 'private' => 95_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس', 'price' => 50_000, 'capacity' => 18, 'days' => [[self::THU, '18:00', '19:30']]],
                    ],
                ],
            ],

            // ─── العلوم ───────────────────────────────────────────────────
            'العلوم' => [
                [
                    'email' => 'salem@altafawwuq.com', 'name' => 'أ. سالم المري',
                    'experience' => 13, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلم علوم — الإعدادي',
                    'bio' => 'العلوم في هذه المرحلة تُبنى بالتجربة والسؤال لا بالتلقين. حصصي قائمة على تجارب بسيطة يعيدها الطالب في البيت.',
                    'grades' => self::PREPARATORY, 'private' => 70_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت',            'price' => 38_000, 'capacity' => 25, 'days' => [[self::SAT, '11:00', '12:30']]],
                        ['name' => 'مجموعة الأحد والثلاثاء',  'price' => 35_000, 'capacity' => 22, 'days' => [[self::SUN, '16:00', '17:00'], [self::TUE, '16:00', '17:00']]],
                    ],
                ],
                [
                    'email' => 'reem@altafawwuq.com', 'name' => 'أ. ريم الدرويش',
                    'experience' => 8, 'commission' => 22,
                    'headline' => 'معلمة علوم — المرحلة الابتدائية',
                    'bio' => 'أحوّل كل درس إلى سؤال يثير فضول الطفل، فيتذكر الإجابة لأنه أرادها لا لأنه طُلب منه حفظها.',
                    'grades' => self::PRIMARY_UPPER, 'private' => 52_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس',      'price' => 26_000, 'capacity' => 25, 'days' => [[self::THU, '16:00', '17:00']]],
                        ['name' => 'مجموعة الاثنين',     'price' => 26_000, 'capacity' => 22, 'days' => [[self::MON, '15:00', '16:00']]],
                    ],
                ],
                [
                    'email' => 'hind@altafawwuq.com', 'name' => 'أ. هند العامري',
                    'experience' => 6, 'commission' => 22,
                    'headline' => 'معلمة علوم — الصفوف الأولى',
                    'bio' => 'أعلّم العلوم للأطفال الصغار بأسلوب الاستكشاف والتجريب المنزلي البسيط، لبناء الفضول العلمي من المرحلة الأولى.',
                    'grades' => self::PRIMARY_LOWER, 'private' => 40_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت والاثنين', 'price' => 22_000, 'capacity' => 25, 'days' => [[self::SAT, '10:00', '11:00'], [self::MON, '10:00', '11:00']]],
                    ],
                ],
            ],

            // ─── اللغة العربية ────────────────────────────────────────────
            'اللغة العربية' => [
                [
                    'email' => 'noura@altafawwuq.com', 'name' => 'أ. نورة العطية',
                    'experience' => 16, 'featured' => true, 'commission' => 18,
                    'headline' => 'معلمة لغة عربية — المرحلة الثانوية',
                    'bio' => 'النحو ليس قواعد تُحفظ بل منطق يُفهم. أدرّس العربية بأسلوب يربط القاعدة بالنص الأدبي، ويعالج ضعف الإملاء والتعبير من جذوره.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_ARTS], 'private' => 90_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد',    'price' => 45_000, 'capacity' => 24, 'days' => [[self::SUN, '18:00', '19:30']]],
                        ['name' => 'مجموعة الأربعاء', 'price' => 45_000, 'capacity' => 24, 'days' => [[self::WED, '15:00', '16:30']]],
                    ],
                ],
                [
                    'email' => 'ibrahim@altafawwuq.com', 'name' => 'أ. إبراهيم الخليفي',
                    'experience' => 9, 'commission' => 20,
                    'headline' => 'معلم لغة عربية — الابتدائي والإعدادي',
                    'bio' => 'أعالج ضعف القراءة والإملاء من جذوره قبل أي شيء، لأن الطالب الذي لا يقرأ جيداً يتعثر في كل مادة أخرى.',
                    'grades' => [...self::PREPARATORY, ...self::PRIMARY_UPPER], 'private' => 62_000,
                    'groups' => [
                        ['name' => 'مجموعة الإثنين والخميس', 'price' => 33_000, 'capacity' => 28, 'days' => [[self::MON, '15:00', '16:00'], [self::THU, '15:00', '16:00']]],
                        ['name' => 'مجموعة الأربعاء',        'price' => 30_000, 'capacity' => 25, 'days' => [[self::WED, '16:30', '17:30']]],
                    ],
                ],
                [
                    'email' => 'lulwa@altafawwuq.com', 'name' => 'أ. لولوة البوعينين',
                    'experience' => 7, 'commission' => 22,
                    'headline' => 'معلمة لغة عربية — الصفوف الأولى',
                    'bio' => 'أعلّم القراءة والكتابة للأطفال بأساليب مبتكرة تجعل تعلّم اللغة العربية تجربة ممتعة وسلسة.',
                    'grades' => self::PRIMARY_LOWER, 'private' => 45_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 24_000, 'capacity' => 25, 'days' => [[self::SAT, '09:00', '10:00']]],
                    ],
                ],
            ],

            // ─── اللغة الإنجليزية ─────────────────────────────────────────
            'اللغة الإنجليزية' => [
                [
                    'email' => 'yousef@altafawwuq.com', 'name' => 'أ. يوسف الحداد',
                    'experience' => 11, 'commission' => 20,
                    'headline' => 'معلم لغة إنجليزية — قواعد ومحادثة',
                    'bio' => 'أجمع بين إتقان القواعد المطلوبة في المنهج وبين بناء ثقة الطالب في التحدث، مع تدريب مكثف على الكتابة ومهارات الامتحان.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_ARTS, ...self::SECONDARY_TECH], 'private' => 100_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء والخميس', 'price' => 55_000, 'capacity' => 20, 'days' => [[self::TUE, '17:30', '19:00'], [self::THU, '17:30', '19:00']]],
                        ['name' => 'مجموعة الأحد المكثفة',    'price' => 60_000, 'capacity' => 14, 'days' => [[self::SUN, '10:00', '12:30']]],
                    ],
                ],
                [
                    'email' => 'dana@altafawwuq.com', 'name' => 'أ. دانة الكبيسي',
                    'experience' => 7, 'commission' => 22,
                    'headline' => 'معلمة لغة إنجليزية — الابتدائي والإعدادي',
                    'bio' => 'الطفل يتعلم اللغة كما تعلّم لغته الأولى: سماعاً وتكراراً قبل القاعدة. حصصي كلها بالإنجليزية مع دعم بالعربية عند الحاجة.',
                    'grades' => [...self::PREPARATORY, ...self::PRIMARY_UPPER], 'private' => 65_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد',            'price' => 36_000, 'capacity' => 26, 'days' => [[self::SUN, '14:00', '15:30']]],
                        ['name' => 'مجموعة الأربعاء والجمعة', 'price' => 34_000, 'capacity' => 24, 'days' => [[self::WED, '14:00', '15:30']]],
                    ],
                ],
                [
                    'email' => 'khalifa@altafawwuq.com', 'name' => 'أ. خليفة العبيدلي',
                    'experience' => 9, 'commission' => 20,
                    'headline' => 'معلم لغة إنجليزية — الصفوف الأولى والثانية',
                    'bio' => 'أبني أساس اللغة الإنجليزية عند الأطفال الصغار من خلال الأغاني والقصص التفاعلية، مما يجعل التعلم تجربة ممتعة.',
                    'grades' => self::PRIMARY_LOWER, 'private' => 42_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين والأربعاء', 'price' => 22_000, 'capacity' => 25, 'days' => [[self::MON, '15:00', '16:00'], [self::WED, '15:00', '16:00']]],
                    ],
                ],
            ],

            // ─── التربية الإسلامية ────────────────────────────────────────
            'التربية الإسلامية' => [
                [
                    'email' => 'abdullah@altafawwuq.com', 'name' => 'أ. عبدالله الشمري',
                    'experience' => 8, 'commission' => 22,
                    'headline' => 'معلم تربية إسلامية — الابتدائي والإعدادي',
                    'bio' => 'أدرّس التربية الإسلامية بأسلوب قصصي مبسّط يناسب الأعمار الصغيرة، مع تركيز على التجويد وحفظ جزء عمّ.',
                    'grades' => [...self::PREPARATORY, ...self::PRIMARY_UPPER], 'private' => 55_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء',    'price' => 28_000, 'capacity' => 30, 'days' => [[self::WED, '16:00', '17:00']]],
                        ['name' => 'مجموعة السبت',       'price' => 30_000, 'capacity' => 26, 'days' => [[self::SAT, '09:00', '10:30']]],
                    ],
                ],
                [
                    'email' => 'hamad@altafawwuq.com', 'name' => 'أ. حمد الرميحي',
                    'experience' => 12, 'commission' => 20,
                    'headline' => 'معلم تربية إسلامية — المرحلة الثانوية',
                    'bio' => 'منهج الثانوية يحتاج فهماً للمقاصد لا حفظاً للنصوص. أربط كل موضوع بواقع الطالب حتى يصبح الدرس مقنعاً.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_ARTS], 'private' => 70_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت',    'price' => 34_000, 'capacity' => 26, 'days' => [[self::SAT, '14:00', '15:00']]],
                        ['name' => 'مجموعة الثلاثاء', 'price' => 32_000, 'capacity' => 28, 'days' => [[self::TUE, '15:00', '16:00']]],
                    ],
                ],
                [
                    'email' => 'moza@altafawwuq.com', 'name' => 'أ. موزة السليطي',
                    'experience' => 6, 'commission' => 22,
                    'headline' => 'معلمة تربية إسلامية — الصفوف الأولى',
                    'bio' => 'أعلّم الطفل أساسيات دينه بالقصة والنشيد والنشاط، فيحبّ دينه قبل أن يحفظ أحكامه.',
                    'grades' => self::PRIMARY_LOWER, 'private' => 38_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين', 'price' => 20_000, 'capacity' => 28, 'days' => [[self::MON, '16:00', '17:00']]],
                    ],
                ],
            ],

            // ─── التاريخ ──────────────────────────────────────────────────
            'التاريخ' => [
                [
                    'email' => 'maryam@altafawwuq.com', 'name' => 'أ. مريم الدوسري',
                    'experience' => 10, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلمة تاريخ — مسار الآداب والإنسانيات',
                    'bio' => 'أحوّل التاريخ من تواريخ تُحفظ إلى قصة مترابطة تُفهم أسبابها ونتائجها، فيصبح الاسترجاع تحصيل حاصل.',
                    'grades' => self::SECONDARY_ARTS, 'private' => 85_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت',    'price' => 45_000, 'capacity' => 20, 'days' => [[self::SAT, '16:00', '17:30']]],
                        ['name' => 'مجموعة الأربعاء', 'price' => 42_000, 'capacity' => 22, 'days' => [[self::WED, '18:00', '19:30']]],
                    ],
                ],
                [
                    'email' => 'saeed@altafawwuq.com', 'name' => 'أ. سعيد المالكي',
                    'experience' => 14, 'commission' => 18,
                    'headline' => 'معلم تاريخ — الإعدادي والثانوي',
                    'bio' => 'أدرّس التاريخ بالخرائط والخطوط الزمنية، فيرى الطالب الحدث في مكانه وزمانه بدل أن يحفظه معزولاً.',
                    'grades' => [...self::SECONDARY_ARTS, ...self::PREPARATORY], 'private' => 78_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء', 'price' => 40_000, 'capacity' => 22, 'days' => [[self::TUE, '18:00', '19:30']]],
                        ['name' => 'مجموعة الاثنين',  'price' => 38_000, 'capacity' => 24, 'days' => [[self::MON, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'talal@altafawwuq.com', 'name' => 'أ. طلال المهندي',
                    'experience' => 5, 'commission' => 22,
                    'headline' => 'معلم تاريخ — الحضارات الإسلامية',
                    'bio' => 'أتخصص في الحضارة الإسلامية وتاريخ الجزيرة العربية، وأربطها بالهوية الوطنية لتثبت في ذهن الطالب.',
                    'grades' => [...self::SECONDARY_ARTS, ...self::PREPARATORY], 'private' => 72_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس', 'price' => 38_000, 'capacity' => 22, 'days' => [[self::THU, '17:00', '18:30']]],
                    ],
                ],
            ],

            // ─── الجغرافيا ────────────────────────────────────────────────
            'الجغرافيا' => [
                [
                    'email' => 'latifa@altafawwuq.com', 'name' => 'أ. لطيفة السويدي',
                    'experience' => 8, 'commission' => 20,
                    'headline' => 'معلمة جغرافيا — مسار الآداب والإنسانيات',
                    'bio' => 'الجغرافيا خرائط تُقرأ لا أسماء تُحفظ. أدرّب الطالب على تحليل الخريطة والرسم البياني قبل أي شيء.',
                    'grades' => self::SECONDARY_ARTS, 'private' => 85_000,
                    'groups' => [
                        ['name' => 'مجموعة الإثنين',  'price' => 45_000, 'capacity' => 20, 'days' => [[self::MON, '19:30', '21:00']]],
                        ['name' => 'مجموعة الأربعاء', 'price' => 42_000, 'capacity' => 20, 'days' => [[self::WED, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'ghalia@altafawwuq.com', 'name' => 'أ. غالية النعمة',
                    'experience' => 6, 'commission' => 22,
                    'headline' => 'معلمة جغرافيا — الإعدادي',
                    'bio' => 'أبدأ من جغرافيا قطر والخليج التي يعرفها الطالب، ثم أوسّع الدائرة، فتصبح المعلومة مرتبطة بشيء يراه.',
                    'grades' => self::PREPARATORY, 'private' => 60_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء', 'price' => 32_000, 'capacity' => 24, 'days' => [[self::WED, '14:00', '15:00']]],
                        ['name' => 'مجموعة الثلاثاء', 'price' => 30_000, 'capacity' => 24, 'days' => [[self::TUE, '16:00', '17:00']]],
                    ],
                ],
                [
                    'email' => 'rana@altafawwuq.com', 'name' => 'أ. رنا الهاجري',
                    'experience' => 9, 'commission' => 20,
                    'headline' => 'معلمة جغرافيا — الجغرافيا البشرية والاقتصادية',
                    'bio' => 'أركّز على الجغرافيا البشرية والاقتصادية بتحليل البيانات والإحصائيات الجغرافية، مما يميّز الطالب في الامتحانات.',
                    'grades' => [...self::SECONDARY_ARTS, 'grade_10'], 'private' => 80_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 40_000, 'capacity' => 22, 'days' => [[self::SAT, '15:00', '16:30']]],
                    ],
                ],
            ],

            // ─── الدراسات الاجتماعية ──────────────────────────────────────
            'الدراسات الاجتماعية' => [
                [
                    'email' => 'jaber@altafawwuq.com', 'name' => 'أ. جابر الفضالة',
                    'experience' => 9, 'commission' => 20,
                    'headline' => 'معلم دراسات اجتماعية — الإعدادي والعاشر',
                    'bio' => 'المادة تجمع التاريخ والجغرافيا والمواطنة، وأدرّسها كوحدة واحدة مترابطة بدل ثلاثة مواضيع منفصلة.',
                    'grades' => [...self::PREPARATORY, 'grade_10'], 'private' => 62_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد',   'price' => 33_000, 'capacity' => 26, 'days' => [[self::SUN, '17:00', '18:00']]],
                        ['name' => 'مجموعة الثلاثاء', 'price' => 31_000, 'capacity' => 24, 'days' => [[self::TUE, '17:00', '18:00']]],
                    ],
                ],
                [
                    'email' => 'shaikha@altafawwuq.com', 'name' => 'أ. شيخة النصر',
                    'experience' => 11, 'commission' => 20,
                    'headline' => 'معلمة دراسات اجتماعية — الابتدائي',
                    'bio' => 'أعرّف الطفل بوطنه ومجتمعه بأنشطة ومشاريع صغيرة يقدّمها بنفسه، فيرسخ المفهوم بالممارسة.',
                    'grades' => self::PRIMARY_UPPER, 'private' => 48_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء', 'price' => 25_000, 'capacity' => 28, 'days' => [[self::TUE, '14:00', '15:00']]],
                        ['name' => 'مجموعة الخميس',   'price' => 25_000, 'capacity' => 26, 'days' => [[self::THU, '14:00', '15:00']]],
                    ],
                ],
                [
                    'email' => 'jasem_s@altafawwuq.com', 'name' => 'أ. جاسم السويدي',
                    'experience' => 7, 'commission' => 22,
                    'headline' => 'معلم دراسات اجتماعية — الصفوف الأولى',
                    'bio' => 'أعلّم التربية الوطنية للأطفال من خلال قصص عن قطر وأبطالها، مما يزرع الانتماء والحب للوطن في نفوس الأجيال الصغيرة.',
                    'grades' => self::PRIMARY_LOWER, 'private' => 38_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء', 'price' => 20_000, 'capacity' => 28, 'days' => [[self::WED, '15:00', '16:00']]],
                    ],
                ],
            ],

            // ─── علوم الحاسب ─────────────────────────────────────────────
            'علوم الحاسب' => [
                [
                    'email' => 'jassim@altafawwuq.com', 'name' => 'أ. جاسم البوعينين',
                    'experience' => 9, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلم علوم حاسب — المسار التكنولوجي',
                    'bio' => 'أدرّس البرمجة وقواعد البيانات وتصميم الشبكات بمشاريع عملية يبنيها الطالب بنفسه بدل الحفظ.',
                    'grades' => [...self::SECONDARY_TECH, 'grade_12_science', 'grade_11_science'], 'private' => 110_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء',  'price' => 58_000, 'capacity' => 18, 'days' => [[self::SUN, '17:00', '18:30'], [self::TUE, '17:00', '18:30']]],
                        ['name' => 'مجموعة الخميس المكثفة',   'price' => 65_000, 'capacity' => 14, 'days' => [[self::THU, '09:00', '12:00']]],
                    ],
                ],
                [
                    'email' => 'nasser@altafawwuq.com', 'name' => 'أ. ناصر الخاطر',
                    'experience' => 5, 'commission' => 22,
                    'headline' => 'معلم علوم حاسب — أساسيات البرمجة',
                    'bio' => 'أبدأ من الصفر بلغة بايثون، ومن أول حصة يكتب الطالب برنامجاً يعمل. لا نظريات قبل أن يرى نتيجة.',
                    'grades' => self::SECONDARY_TECH, 'private' => 95_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس', 'price' => 50_000, 'capacity' => 20, 'days' => [[self::THU, '18:00', '19:30']]],
                        ['name' => 'مجموعة السبت',  'price' => 52_000, 'capacity' => 18, 'days' => [[self::SAT, '14:00', '16:00']]],
                    ],
                ],
                [
                    'email' => 'sarah_cs@altafawwuq.com', 'name' => 'أ. سارة الخليفي',
                    'experience' => 8, 'commission' => 20,
                    'headline' => 'معلمة علوم حاسب — قواعد البيانات والشبكات',
                    'bio' => 'أتخصص في تدريس قواعد البيانات SQL وتصميم الشبكات لطلاب المسار التكنولوجي، مع التدريب على المشاريع العملية.',
                    'grades' => [...self::SECONDARY_TECH, 'grade_10'], 'private' => 100_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين والأربعاء', 'price' => 55_000, 'capacity' => 18, 'days' => [[self::MON, '17:00', '18:30'], [self::WED, '17:00', '18:30']]],
                    ],
                ],
            ],

            // ─── تكنولوجيا المعلومات ──────────────────────────────────────
            'تكنولوجيا المعلومات' => [
                [
                    'email' => 'rashid@altafawwuq.com', 'name' => 'أ. راشد الخاطر',
                    'experience' => 7, 'commission' => 20,
                    'headline' => 'معلم تكنولوجيا المعلومات',
                    'bio' => 'من أساسيات الحاسب حتى بناء موقع كامل — كل حصة ينتج فيها الطالب شيئاً يشغّله بنفسه.',
                    'grades' => [...self::SECONDARY_TECH, 'grade_10', ...self::PREPARATORY], 'private' => 90_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء',   'price' => 48_000, 'capacity' => 20, 'days' => [[self::WED, '19:00', '20:30']]],
                        ['name' => 'مجموعة الثلاثاء',   'price' => 45_000, 'capacity' => 22, 'days' => [[self::TUE, '16:00', '17:30']]],
                    ],
                ],
                [
                    'email' => 'amal@altafawwuq.com', 'name' => 'أ. أمل الأنصاري',
                    'experience' => 6, 'commission' => 22,
                    'headline' => 'معلمة تكنولوجيا المعلومات — الابتدائي',
                    'bio' => 'أعلّم الطفل استخدام الحاسب بأمان ومهارة، من الطباعة حتى البرمجة المرئية بسكراتش.',
                    'grades' => self::PRIMARY_UPPER, 'private' => 45_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت',   'price' => 24_000, 'capacity' => 24, 'days' => [[self::SAT, '10:00', '11:00']]],
                        ['name' => 'مجموعة الخميس',  'price' => 24_000, 'capacity' => 24, 'days' => [[self::THU, '15:00', '16:00']]],
                    ],
                ],
                [
                    'email' => 'faisal@altafawwuq.com', 'name' => 'أ. فيصل المناعي',
                    'experience' => 10, 'commission' => 20,
                    'headline' => 'معلم تكنولوجيا المعلومات — الصفوف الأولى',
                    'bio' => 'أُعرّف الأطفال الصغار بالعالم الرقمي بطريقة آمنة وممتعة، من استخدام الجهاز اللوحي حتى أساسيات برنامج Word.',
                    'grades' => self::PRIMARY_LOWER, 'private' => 38_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين', 'price' => 20_000, 'capacity' => 25, 'days' => [[self::MON, '14:00', '15:00']]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Flat list of all teachers with their subject attached (for AccountsSeeder).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function teachers(): array
    {
        $teachers = [];
        $phone    = 101;

        foreach (self::plan() as $subject => $staff) {
            foreach ($staff as $teacher) {
                $teachers[] = [
                    ...$teacher,
                    'subject' => $subject,
                    'phone'   => '+974550001' . str_pad((string) $phone++, 2, '0', STR_PAD_LEFT),
                ];
            }
        }

        return $teachers;
    }
}
