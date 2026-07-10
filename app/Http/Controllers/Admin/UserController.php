<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deactivating themselves
        abort_if($user->id === auth()->id(), 403, 'لا يمكنك تعطيل حسابك الخاص.');

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$status} حساب {$user->name}.");
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,teacher,student'],
        ]);

        $user = User::findOrFail($id);

        // Prevent admin from changing their own role
        abort_if($user->id === auth()->id(), 403, 'لا يمكنك تغيير دورك الخاص.');

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "تم تحديث دور {$user->name} بنجاح.");
    }
}
