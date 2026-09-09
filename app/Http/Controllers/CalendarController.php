<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        $orders = Order::query()
            ->with(['customer:id,name', 'service:id,name', 'staff:id,name'])
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->get()
            ->map(static fn (Order $order): array => [
                'id' => $order->id,
                'title' => $order->title,
                'status' => $order->status,
                'scheduled_at' => optional($order->scheduled_at)->toIso8601String(),
                'customer_name' => $order->customer?->name,
                'service_name' => $order->service?->name,
                'staff_names' => $order->staff->pluck('name')->all(),
                'notes' => $order->notes,
            ])
            ->all();

        return Inertia::render('Calendar/Index', [
            'orders' => $orders,
        ]);
    }
}
