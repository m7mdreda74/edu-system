<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role'   => ['nullable', 'string', 'in:admin,teacher,student'],
        ]);

        $users = User::with('roles:name')
            ->when(! empty($filters['search']), fn ($q) =>
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
            )
            ->when(! empty($filters['role']), fn ($q) =>
                $q->role($filters['role'])
            )
            ->withCount('enrollments')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users'   => $users,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone'       => ['required', 'string', 'max:20', 'unique:users'],
            'password'    => ['required', 'string', 'min:8'],
            'role'        => ['required', 'string', 'in:admin,teacher,student,parent'],
            'grade_level' => ['nullable', 'exists:grade_levels,key'],
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'phone'       => $validated['phone'],
            'password'    => $validated['password'], // hashed by cast
            'grade_level' => ($validated['role'] === 'student') ? ($validated['grade_level'] ?? null) : null,
            'is_active'   => true,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', "تم إضافة المستخدم {$user->name} بنجاح.");
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deactivating themselves
        abort_if($user->id === Auth::id(), 403, 'لا يمكنك تعطيل حسابك الخاص.');

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$status} حساب {$user->name}.");
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,teacher,student,parent'],
        ]);

        $user = User::findOrFail($id);

        // Prevent admin from changing their own role
        abort_if($user->id === Auth::id(), 403, 'لا يمكنك تغيير دورك الخاص.');

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "تم تحديث دور {$user->name} بنجاح.");
    }
}
