<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Booking\Models\BookingPage;

class PublicBookingPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = BookingPage::with([
            'clinic',
            'services' => fn ($q) => $q->where('is_active', true),
            'professionals' => fn ($q) => $q->where('allow_online_booking', true)->with('user:id,name'),
        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            return response()->json(['message' => 'Página de reserva no encontrada.'], 404);
        }

        return response()->json([
            'clinic' => [
                'id' => $page->clinic->id,
                'name' => $page->clinic->name,
                'address' => $page->clinic->address,
                'phone' => $page->clinic->phone,
                'email' => $page->clinic->email,
            ],
            'services' => $page->services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'duration_minutes' => $s->duration_minutes,
                'price' => $s->price,
            ]),
            'professionals' => $page->professionals->map(fn ($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
            ]),
            'settings' => [
                'title' => $page->title,
                'max_horizon_days' => $page->max_horizon_days,
                'cancellation_hours' => $page->cancellation_hours,
            ],
        ]);
    }
}
