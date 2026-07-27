<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ordinals for auto-generated curriculum names — "الوحدة الأولى", "الدرس الأول".
 *
 * Arabic agrees the ordinal with its noun, so the two genders are separate
 * lists rather than one with a suffix rule. A term never runs past a dozen
 * units or lessons in practice, so the table stops there and anything beyond
 * falls back to the bare number.
 */
final class ArabicOrdinal
{
    /** @var array<int, string> */
    private const FEMININE = [
        1  => 'الأولى',
        2  => 'الثانية',
        3  => 'الثالثة',
        4  => 'الرابعة',
        5  => 'الخامسة',
        6  => 'السادسة',
        7  => 'السابعة',
        8  => 'الثامنة',
        9  => 'التاسعة',
        10 => 'العاشرة',
        11 => 'الحادية عشرة',
        12 => 'الثانية عشرة',
    ];

    /** @var array<int, string> */
    private const MASCULINE = [
        1  => 'الأول',
        2  => 'الثاني',
        3  => 'الثالث',
        4  => 'الرابع',
        5  => 'الخامس',
        6  => 'السادس',
        7  => 'السابع',
        8  => 'الثامن',
        9  => 'التاسع',
        10 => 'العاشر',
        11 => 'الحادي عشر',
        12 => 'الثاني عشر',
    ];

    /** For feminine nouns: الوحدة الأولى. */
    public static function feminine(int $number): string
    {
        return self::FEMININE[$number] ?? (string) $number;
    }

    /** For masculine nouns: الدرس الأول. */
    public static function masculine(int $number): string
    {
        return self::MASCULINE[$number] ?? (string) $number;
    }
}
