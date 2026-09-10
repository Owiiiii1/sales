<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics): Response
    {
        $filter = AnalyticsFilter::fromRequest($request);

        return Inertia::render('Dashboard', $analytics->payload($filter));
    }
}
