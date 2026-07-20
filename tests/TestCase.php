<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests create users with application roles directly. Keep the
        // role catalog present after RefreshDatabase resets the test schema.
        if (Schema::hasTable('roles')) {
            foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }
        }
    }
}
