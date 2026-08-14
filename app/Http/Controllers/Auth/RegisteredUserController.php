<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Rules\AltafawwuqEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $request->merge([
            'email' => AltafawwuqEmail::normalize($request->input('email')),
            'phone' => trim((string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', new AltafawwuqEmail(), 'unique:users'],
            'phone'       => ['required', 'string', 'max:20', 'unique:users'],
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
            // Teacher is a privileged role and must be assigned by an admin.
            // Never trust a client-supplied role to create teaching access.
            'role'        => ['required', 'in:student,parent'],
            'grade_level' => [
                'required_if:role,student',
                'nullable',
                Rule::exists('grade_levels', 'key')
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->where('key', '!=', 'all')),
            ],
        ], [
            'grade_level.required_if' => 'اختر المرحلة والصف الدراسي للطالب.',
            'grade_level.exists' => 'الصف الدراسي المختار غير متاح حالياً.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'phone'       => $validated['phone'],
                'password'    => $validated['password'],
                'grade_level' => $validated['grade_level'] ?? null,
                'is_active'   => true,
            ]);

            $user->assignRole($validated['role']);

            return $user;
        });

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
