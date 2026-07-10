<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Quiz;

use App\Domain\Course\Models\Course;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    public function definition(): array
    {
        return [
            'course_id'          => Course::factory(),
            'title'              => 'اختبار ' . $this->faker->word(),
            'passing_score'      => 70,
            'time_limit_minutes' => null,
            'is_active'          => true,
        ];
    }

    /**
     * Add N questions, each with 4 options (1 correct).
     */
    public function withQuestions(int $count = 3): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $question = QuizQuestion::create([
                    'quiz_id'       => $quiz->id,
                    'question_text' => 'سؤال رقم ' . ($i + 1) . ': ' . $this->faker->sentence(),
                    'type'          => 'single',
                    'order'         => $i + 1,
                ]);

                // 1 correct option
                QuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'الإجابة الصحيحة',
                    'is_correct'  => true,
                ]);

                // 3 wrong options
                for ($j = 2; $j <= 4; $j++) {
                    QuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => 'إجابة خاطئة ' . $j,
                        'is_correct'  => false,
                    ]);
                }
            }
        });
    }

    public function withTimeLimit(int $minutes = 30): static
    {
        return $this->state(['time_limit_minutes' => $minutes]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
