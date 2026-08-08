<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly ParentStudentLinkService $parentStudentLinks,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => trim((string) $request->input('phone')),
            'parent_phone' => trim((string) $request->input('parent_phone')),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone'       => ['required', 'string', 'max:20', 'unique:users'],
            'parent_phone' => ['nullable', 'required_if:role,student', 'string', 'max:20', 'different:phone'],
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
            'role'        => ['required', 'in:student,teacher,parent'],
            'grade_level' => ['nullable', 'exists:grade_levels,key'],
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

            if ($validated['role'] === 'student') {
                $this->parentStudentLinks->linkExistingParent($user, $validated['parent_phone']);
            }

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
