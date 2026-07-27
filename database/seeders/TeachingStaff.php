<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * The demo faculty, defined subject-first.
 *
 * A teacher is declared *inside* the subject they teach, which makes "one
 * subject per teacher" structurally impossible to violate here rather than
 * something the seeders have to remember. The reverse — many teachers to a
 * subject — is the whole point of the browse flow, so every subject worth
 * studying online carries at least two.
 *
 * Prices are in the smallest currency unit: 60000 is 600 riyals a month.
 * Secondary costs more than primary, and private more than group.
 */
final class TeachingStaff
{
    /** Days of the week, Sunday first, matching Carbon's dayOfWeek. */
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
     * subject name => the teachers who teach it.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function plan(): array
    {
        return [
            'الرياضيات' => [
                [
                    'email' => 'ahmed@altafawwuq.com', 'name' => 'أ. أحمد الكواري', 'experience' => 14, 'featured' => true, 'commission' => 18,
                    'headline' => 'معلم رياضيات للثانوية — 14 سنة خبرة',
                    'bio' => 'أدرّس الرياضيات من الأساس: كل قاعدة تبدأ بسؤال من امتحان وزاري حقيقي، ثم نبنيها خطوة خطوة حتى يصبح الحل بديهياً. أركّز على التفاضل والتكامل والهندسة التحليلية.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 120_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 60_000, 'capacity' => 18, 'days' => [[self::SUN, '16:00', '17:30'], [self::TUE, '16:00', '17:30']]],
                        ['name' => 'مجموعة السبت المكثفة',   'price' => 75_000, 'capacity' => 12, 'days' => [[self::SAT, '09:00', '11:30']]],
                    ],
                ],
                [
                    'email' => 'mona@altafawwuq.com', 'name' => 'أ. منى العبيدلي', 'experience' => 11, 'commission' => 20,
                    'headline' => 'معلمة رياضيات — الإعدادي والمسار الأدبي',
                    'bio' => 'كثير من الطلاب يكرهون الرياضيات لأنهم فاتتهم خطوة في الإعدادي. أبدأ من حيث توقف الطالب فعلاً، لا من حيث يفترض المنهج.',
                    'grades' => [...self::PREPARATORY, ...self::SECONDARY_ARTS], 'private' => 80_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين والأربعاء', 'price' => 42_000, 'capacity' => 22, 'days' => [[self::MON, '18:00', '19:30'], [self::WED, '18:00', '19:30']]],
                    ],
                ],
                [
                    'email' => 'fatima@altafawwuq.com', 'name' => 'أ. فاطمة النعيمي', 'experience' => 12, 'commission' => 22,
                    'headline' => 'معلمة رياضيات — المرحلة الابتدائية',
                    'bio' => 'أبني الأساس الصحيح للطفل في الحساب عبر الأنشطة والألعاب التعليمية، لأن ضعف الابتدائي يلاحق الطالب لسنوات.',
                    'grades' => [...self::PRIMARY_UPPER, ...self::PRIMARY_LOWER], 'private' => 50_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 28_000, 'capacity' => 25, 'days' => [[self::SUN, '15:00', '16:00'], [self::TUE, '15:00', '16:00']]],
                    ],
                ],
            ],

            'الفيزياء' => [
                [
                    'email' => 'sara@altafawwuq.com', 'name' => 'أ. سارة المهندي', 'experience' => 9, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلمة فيزياء — الشرح بالتجربة',
                    'bio' => 'كل درس يبدأ بتجربة أو موقف من الحياة اليومية قبل أي قانون. الطالب يفهم لماذا قبل أن يحفظ كيف، وهذا ما يجعل المعلومة تثبت حتى يوم الامتحان.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_TECH], 'private' => 125_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء',        'price' => 65_000, 'capacity' => 16, 'days' => [[self::WED, '17:00', '18:30']]],
                        ['name' => 'مجموعة الخميس المسائية', 'price' => 65_000, 'capacity' => 16, 'days' => [[self::THU, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'tariq@altafawwuq.com', 'name' => 'أ. طارق الأنصاري', 'experience' => 15, 'commission' => 18,
                    'headline' => 'معلم فيزياء — حل المسائل الوزارية',
                    'bio' => 'تركيزي كله على المسألة: كيف تقرأها، وكيف تختار القانون، وكيف تتفادى الأخطاء التي تتكرر كل عام في التصحيح.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 130_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 70_000, 'capacity' => 14, 'days' => [[self::SAT, '17:00', '19:00']]],
                    ],
                ],
            ],

            'الكيمياء' => [
                [
                    'email' => 'hessa@altafawwuq.com', 'name' => 'أ. حصة الكواري', 'experience' => 10, 'commission' => 20,
                    'headline' => 'معلمة كيمياء — من العاشر إلى الثاني عشر',
                    'bio' => 'أربط كل تفاعل بمثال من الحياة أو الصناعة، فيصبح الحفظ نتيجة للفهم لا بديلاً عنه.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 110_000,
                    'groups' => [
                        ['name' => 'مجموعة الاثنين', 'price' => 55_000, 'capacity' => 20, 'days' => [[self::MON, '16:30', '18:00']]],
                    ],
                ],
                [
                    'email' => 'badr@altafawwuq.com', 'name' => 'أ. بدر الهاجري', 'experience' => 6, 'commission' => 22,
                    'headline' => 'معلم كيمياء — الكيمياء العضوية خطوة بخطوة',
                    'bio' => 'العضوية ليست حفظاً لسلاسل، بل منطق يتكرر. أعطي الطالب المنطق مرة واحدة فيحل ما لم يره من قبل.',
                    'grades' => ['grade_12_science', 'grade_11_science'], 'private' => 105_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس', 'price' => 52_000, 'capacity' => 18, 'days' => [[self::THU, '16:00', '17:30']]],
                    ],
                ],
            ],

            'الأحياء' => [
                [
                    'email' => 'khaled@altafawwuq.com', 'name' => 'أ. خالد آل ثاني', 'experience' => 7, 'commission' => 20,
                    'headline' => 'معلم أحياء — خرائط ذهنية وملخصات',
                    'bio' => 'أسلوبي يعتمد على الخرائط الذهنية التي تختصر الوحدة كاملة في صفحة واحدة، مع مراجعات مركّزة قبل الامتحانات مباشرة.',
                    'grades' => self::SECONDARY_SCIENCE, 'private' => 100_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء', 'price' => 52_000, 'capacity' => 20, 'days' => [[self::TUE, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'aisha@altafawwuq.com', 'name' => 'أ. عائشة المسند', 'experience' => 13, 'featured' => true, 'commission' => 18,
                    'headline' => 'معلمة أحياء — للراغبين في كليات الطب',
                    'bio' => 'أدرّس الأحياء بعمق يتجاوز المنهج قليلاً لمن ينوي الطب، مع تدريب على أسئلة التفكير لا الاسترجاع.',
                    'grades' => ['grade_12_science', 'grade_11_science'], 'private' => 115_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد', 'price' => 58_000, 'capacity' => 16, 'days' => [[self::SUN, '19:00', '20:30']]],
                    ],
                ],
            ],

            'العلوم' => [
                [
                    'email' => 'salem@altafawwuq.com', 'name' => 'أ. سالم المري', 'experience' => 13, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلم علوم — الإعدادي',
                    'bio' => 'العلوم في هذه المرحلة تُبنى بالتجربة والسؤال لا بالتلقين. حصصي قائمة على تجارب بسيطة يعيدها الطالب في البيت.',
                    'grades' => self::PREPARATORY, 'private' => 70_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 38_000, 'capacity' => 25, 'days' => [[self::SAT, '11:00', '12:30']]],
                    ],
                ],
                [
                    'email' => 'reem@altafawwuq.com', 'name' => 'أ. ريم الدرويش', 'experience' => 8, 'commission' => 22,
                    'headline' => 'معلمة علوم — المرحلة الابتدائية',
                    'bio' => 'أحوّل كل درس إلى سؤال يثير فضول الطفل، فيتذكر الإجابة لأنه أرادها لا لأنه طُلب منه حفظها.',
                    'grades' => self::PRIMARY_UPPER, 'private' => 52_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس', 'price' => 26_000, 'capacity' => 25, 'days' => [[self::THU, '16:00', '17:00']]],
                    ],
                ],
            ],

            'اللغة العربية' => [
                [
                    'email' => 'noura@altafawwuq.com', 'name' => 'أ. نورة العطية', 'experience' => 16, 'featured' => true, 'commission' => 18,
                    'headline' => 'معلمة لغة عربية — المرحلة الثانوية',
                    'bio' => 'النحو ليس قواعد تُحفظ بل منطق يُفهم. أدرّس العربية بأسلوب يربط القاعدة بالنص الأدبي، ويعالج ضعف الإملاء والتعبير من جذوره.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_ARTS], 'private' => 90_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد',    'price' => 45_000, 'capacity' => 24, 'days' => [[self::SUN, '18:00', '19:30']]],
                        ['name' => 'مجموعة الأربعاء', 'price' => 45_000, 'capacity' => 24, 'days' => [[self::WED, '15:00', '16:30']]],
                    ],
                ],
                [
                    'email' => 'ibrahim@altafawwuq.com', 'name' => 'أ. إبراهيم الخليفي', 'experience' => 9, 'commission' => 20,
                    'headline' => 'معلم لغة عربية — الابتدائي والإعدادي',
                    'bio' => 'أعالج ضعف القراءة والإملاء من جذوره قبل أي شيء، لأن الطالب الذي لا يقرأ جيداً يتعثر في كل مادة أخرى.',
                    'grades' => [...self::PREPARATORY, ...self::PRIMARY_UPPER], 'private' => 62_000,
                    'groups' => [
                        ['name' => 'مجموعة الإثنين والخميس', 'price' => 33_000, 'capacity' => 28, 'days' => [[self::MON, '15:00', '16:00'], [self::THU, '15:00', '16:00']]],
                    ],
                ],
            ],

            'اللغة الإنجليزية' => [
                [
                    'email' => 'yousef@altafawwuq.com', 'name' => 'أ. يوسف الحداد', 'experience' => 11, 'commission' => 20,
                    'headline' => 'معلم لغة إنجليزية — قواعد ومحادثة',
                    'bio' => 'أجمع بين إتقان القواعد المطلوبة في المنهج وبين بناء ثقة الطالب في التحدث، مع تدريب مكثف على الكتابة ومهارات الامتحان.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_ARTS, ...self::SECONDARY_TECH], 'private' => 100_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء والخميس', 'price' => 55_000, 'capacity' => 20, 'days' => [[self::TUE, '17:30', '19:00'], [self::THU, '17:30', '19:00']]],
                    ],
                ],
                [
                    'email' => 'dana@altafawwuq.com', 'name' => 'أ. دانة الكبيسي', 'experience' => 7, 'commission' => 22,
                    'headline' => 'معلمة لغة إنجليزية — الابتدائي والإعدادي',
                    'bio' => 'الطفل يتعلم اللغة كما تعلّم لغته الأولى: سماعاً وتكراراً قبل القاعدة. حصصي كلها بالإنجليزية مع دعم بالعربية عند الحاجة.',
                    'grades' => [...self::PREPARATORY, ...self::PRIMARY_UPPER], 'private' => 65_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد', 'price' => 36_000, 'capacity' => 26, 'days' => [[self::SUN, '14:00', '15:30']]],
                    ],
                ],
            ],

            'التربية الإسلامية' => [
                [
                    'email' => 'abdullah@altafawwuq.com', 'name' => 'أ. عبدالله الشمري', 'experience' => 8, 'commission' => 22,
                    'headline' => 'معلم تربية إسلامية — الابتدائي والإعدادي',
                    'bio' => 'أدرّس التربية الإسلامية بأسلوب قصصي مبسّط يناسب الأعمار الصغيرة، مع تركيز على التجويد وحفظ جزء عمّ.',
                    'grades' => [...self::PREPARATORY, ...self::PRIMARY_UPPER], 'private' => 55_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء', 'price' => 28_000, 'capacity' => 30, 'days' => [[self::WED, '16:00', '17:00']]],
                    ],
                ],
                [
                    'email' => 'hamad@altafawwuq.com', 'name' => 'أ. حمد الرميحي', 'experience' => 12, 'commission' => 20,
                    'headline' => 'معلم تربية إسلامية — المرحلة الثانوية',
                    'bio' => 'منهج الثانوية يحتاج فهماً للمقاصد لا حفظاً للنصوص. أربط كل موضوع بواقع الطالب حتى يصبح الدرس مقنعاً.',
                    'grades' => [...self::SECONDARY_SCIENCE, ...self::SECONDARY_ARTS], 'private' => 70_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 34_000, 'capacity' => 26, 'days' => [[self::SAT, '14:00', '15:00']]],
                    ],
                ],
            ],

            'التاريخ' => [
                [
                    'email' => 'maryam@altafawwuq.com', 'name' => 'أ. مريم الدوسري', 'experience' => 10, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلمة تاريخ — مسار الآداب والإنسانيات',
                    'bio' => 'أحوّل التاريخ من تواريخ تُحفظ إلى قصة مترابطة تُفهم أسبابها ونتائجها، فيصبح الاسترجاع تحصيل حاصل.',
                    'grades' => self::SECONDARY_ARTS, 'private' => 85_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 45_000, 'capacity' => 20, 'days' => [[self::SAT, '16:00', '17:30']]],
                    ],
                ],
                [
                    'email' => 'saeed@altafawwuq.com', 'name' => 'أ. سعيد المالكي', 'experience' => 14, 'commission' => 18,
                    'headline' => 'معلم تاريخ — الإعدادي والثانوي',
                    'bio' => 'أدرّس التاريخ بالخرائط والخطوط الزمنية، فيرى الطالب الحدث في مكانه وزمانه بدل أن يحفظه معزولاً.',
                    'grades' => [...self::SECONDARY_ARTS, ...self::PREPARATORY], 'private' => 78_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء', 'price' => 40_000, 'capacity' => 22, 'days' => [[self::TUE, '18:00', '19:30']]],
                    ],
                ],
            ],

            'الجغرافيا' => [
                [
                    'email' => 'latifa@altafawwuq.com', 'name' => 'أ. لطيفة السويدي', 'experience' => 8, 'commission' => 20,
                    'headline' => 'معلمة جغرافيا — مسار الآداب والإنسانيات',
                    'bio' => 'الجغرافيا خرائط تُقرأ لا أسماء تُحفظ. أدرّب الطالب على تحليل الخريطة والرسم البياني قبل أي شيء.',
                    'grades' => self::SECONDARY_ARTS, 'private' => 85_000,
                    'groups' => [
                        ['name' => 'مجموعة الإثنين', 'price' => 45_000, 'capacity' => 20, 'days' => [[self::MON, '19:30', '21:00']]],
                    ],
                ],
                [
                    'email' => 'ghalia@altafawwuq.com', 'name' => 'أ. غالية النعمة', 'experience' => 6, 'commission' => 22,
                    'headline' => 'معلمة جغرافيا — الإعدادي',
                    'bio' => 'أبدأ من جغرافيا قطر والخليج التي يعرفها الطالب، ثم أوسّع الدائرة، فتصبح المعلومة مرتبطة بشيء يراه.',
                    'grades' => self::PREPARATORY, 'private' => 60_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء', 'price' => 32_000, 'capacity' => 24, 'days' => [[self::WED, '14:00', '15:00']]],
                    ],
                ],
            ],

            'الدراسات الاجتماعية' => [
                [
                    'email' => 'jaber@altafawwuq.com', 'name' => 'أ. جابر الفضالة', 'experience' => 9, 'commission' => 20,
                    'headline' => 'معلم دراسات اجتماعية — الإعدادي والعاشر',
                    'bio' => 'المادة تجمع التاريخ والجغرافيا والمواطنة، وأدرّسها كوحدة واحدة مترابطة بدل ثلاثة مواضيع منفصلة.',
                    'grades' => [...self::PREPARATORY, 'grade_10'], 'private' => 62_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد', 'price' => 33_000, 'capacity' => 26, 'days' => [[self::SUN, '17:00', '18:00']]],
                    ],
                ],
                [
                    'email' => 'shaikha@altafawwuq.com', 'name' => 'أ. شيخة النصر', 'experience' => 11, 'commission' => 20,
                    'headline' => 'معلمة دراسات اجتماعية — الابتدائي',
                    'bio' => 'أعرّف الطفل بوطنه ومجتمعه بأنشطة ومشاريع صغيرة يقدّمها بنفسه، فيرسخ المفهوم بالممارسة.',
                    'grades' => self::PRIMARY_UPPER, 'private' => 48_000,
                    'groups' => [
                        ['name' => 'مجموعة الثلاثاء', 'price' => 25_000, 'capacity' => 28, 'days' => [[self::TUE, '14:00', '15:00']]],
                    ],
                ],
            ],

            'علوم الحاسب' => [
                [
                    'email' => 'jassim@altafawwuq.com', 'name' => 'أ. جاسم البوعينين', 'experience' => 9, 'featured' => true, 'commission' => 20,
                    'headline' => 'معلم علوم حاسب — المسار التكنولوجي',
                    'bio' => 'أدرّس البرمجة وقواعد البيانات وتصميم الشبكات بمشاريع عملية يبنيها الطالب بنفسه بدل الحفظ.',
                    'grades' => [...self::SECONDARY_TECH, 'grade_12_science', 'grade_11_science'], 'private' => 110_000,
                    'groups' => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 58_000, 'capacity' => 18, 'days' => [[self::SUN, '17:00', '18:30'], [self::TUE, '17:00', '18:30']]],
                    ],
                ],
                [
                    'email' => 'nasser@altafawwuq.com', 'name' => 'أ. ناصر الخاطر', 'experience' => 5, 'commission' => 22,
                    'headline' => 'معلم علوم حاسب — أساسيات البرمجة',
                    'bio' => 'أبدأ من الصفر بلغة بايثون، ومن أول حصة يكتب الطالب برنامجاً يعمل. لا نظريات قبل أن يرى نتيجة.',
                    'grades' => self::SECONDARY_TECH, 'private' => 95_000,
                    'groups' => [
                        ['name' => 'مجموعة الخميس', 'price' => 50_000, 'capacity' => 20, 'days' => [[self::THU, '18:00', '19:30']]],
                    ],
                ],
            ],

            'تكنولوجيا المعلومات' => [
                [
                    'email' => 'rashid@altafawwuq.com', 'name' => 'أ. راشد الخاطر', 'experience' => 7, 'commission' => 20,
                    'headline' => 'معلم تكنولوجيا المعلومات',
                    'bio' => 'من أساسيات الحاسب حتى بناء موقع كامل — كل حصة ينتج فيها الطالب شيئاً يشغّله بنفسه.',
                    'grades' => [...self::SECONDARY_TECH, 'grade_10', ...self::PREPARATORY], 'private' => 90_000,
                    'groups' => [
                        ['name' => 'مجموعة الأربعاء', 'price' => 48_000, 'capacity' => 20, 'days' => [[self::WED, '19:00', '20:30']]],
                    ],
                ],
                [
                    'email' => 'amal@altafawwuq.com', 'name' => 'أ. أمل الأنصاري', 'experience' => 6, 'commission' => 22,
                    'headline' => 'معلمة تكنولوجيا المعلومات — الابتدائي',
                    'bio' => 'أعلّم الطفل استخدام الحاسب بأمان ومهارة، من الطباعة حتى البرمجة المرئية بسكراتش.',
                    'grades' => self::PRIMARY_UPPER, 'private' => 45_000,
                    'groups' => [
                        ['name' => 'مجموعة السبت', 'price' => 24_000, 'capacity' => 24, 'days' => [[self::SAT, '10:00', '11:00']]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Flat list of teachers with their subject attached, for account creation.
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
