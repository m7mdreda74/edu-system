<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
            'role'        => ['required', 'in:student,teacher,parent'],
            'grade_level' => ['nullable', 'exists:grade_levels,key'],
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => $validated['password'],
            'grade_level' => $validated['grade_level'] ?? null,
            'is_active'   => true,
        ]);

        // Assign role from registration form
        $user->assignRole($validated['role']);

        event(new Registered($user));
        Auth::login($user);

        // Redirect based on role
        $redirectRoute = match ($validated['role']) {
            'teacher' => 'teacher.dashboard',
            'parent'  => 'parent.dashboard',
            default   => 'dashboard',
        };

        return redirect()->route($redirectRoute);
    }
}
