<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use PHPUnit\Framework\TestCase;

class TeachingGroupScheduleTest extends TestCase
{
    public function test_formats_time_into_12_hour_arabic_system(): void
    {
        $this->assertSame('7 مساءً', TeachingGroupSchedule::formatTime12('19:00'));
        $this->assertSame('7:30 مساءً', TeachingGroupSchedule::formatTime12('19:30'));
        $this->assertSame('7 صباحاً', TeachingGroupSchedule::formatTime12('07:00'));
        $this->assertSame('7:15 صباحاً', TeachingGroupSchedule::formatTime12('07:15'));
        $this->assertSame('12 مساءً', TeachingGroupSchedule::formatTime12('12:00'));
        $this->assertSame('12:45 مساءً', TeachingGroupSchedule::formatTime12('12:45'));
        $this->assertSame('12 صباحاً', TeachingGroupSchedule::formatTime12('00:00'));
        $this->assertSame('12:10 صباحاً', TeachingGroupSchedule::formatTime12('00:10'));
        $this->assertSame('', TeachingGroupSchedule::formatTime12(null));
        $this->assertSame('', TeachingGroupSchedule::formatTime12(''));
    }
}
