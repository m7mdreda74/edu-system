<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Analytics\Services\AdminAnalyticsService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(AdminAnalyticsService $analytics): Response
    {
        return Inertia::render('Admin/Analytics', [
            'analytics' => $analytics->dashboard(),
        ]);
    }
}
