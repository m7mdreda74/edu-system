<?php

namespace App\Models;

/**
 * Backwards-compatible alias for the domain user model used by the application.
 *
 * The Breeze scaffold still references App\Models\User, while authentication
 * and the rest of the domain use App\Domain\User\Models\User. Extending the
 * domain model keeps both entry points on the same roles, casts, and relations.
 */
class User extends \App\Domain\User\Models\User
{
}
