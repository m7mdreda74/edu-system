<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full application and run against a fresh schema, so
| every test starts from a known-empty database. Directories are listed one by
| one because `Tests\Feature\Admin\ThemeSettingsTest` binds its own test case.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Admin/AdminControlTest.php', 'Feature/Auth', 'Feature/Browse', 'Feature/Curriculum', 'Feature/Subscription');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');
