<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Unit tests use the full TestCase with RefreshDatabase. Feature test files
| each call uses(TestCase::class, RefreshDatabase::class) directly in the
| file so that Intelephense can resolve $this inside Pest closures.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');
