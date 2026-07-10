<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::with(['user:id,name,email', 'course:id,title'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'filters'  => $request->only('status'),
        ]);
    }
}
