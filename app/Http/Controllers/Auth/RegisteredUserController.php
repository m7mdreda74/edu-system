<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\User\Services\ParentStudentLinkService;
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
            'email' => AltafawwuqEmail::normalize($request->input('email')),
            'phone' => trim((string) $request->input('phone')),
            'parent_phone' => trim((string) $request->input('parent_phone')),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', new AltafawwuqEmail(), 'unique:users'],
            'phone'       => ['required', 'string', 'max:20', 'unique:users'],
            'parent_phone' => ['nullable', 'required_if:role,student', 'string', 'max:20', 'different:phone'],
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
            'parent_phone.required_if' => 'يرجى تسجيل رقم ولي الأمر المربوط بالمنصة.',
            'parent_phone.different' => 'يجب أن يكون رقم ولي الأمر مختلفاً عن رقم الطالب.',
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
