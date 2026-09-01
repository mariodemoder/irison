<?php

namespace App\Http\Controllers\API;

use App\Mail\AccountActivationMail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Models\Clinic;
use App\Models\Profile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingProfessional;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request)
    {
        $data = $request->validated();

        // Generate a unique slug for the patient portal.
        $portalSlug = Str::slug($data['clinic_name']);
        if (Clinic::withoutGlobalScopes()->where('slug', $portalSlug)->exists()) {
            $portalSlug .= '-' . Str::random(4);
        }

        $clinic = Clinic::create([
            'name' => $data['clinic_name'],
            'slug' => $portalSlug,
            'legal_name' => $data['clinic_name'],
            'email' => $data['email'],
            'nif' => $data['nif'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'plan' => 'basic',
            'max_users' => Clinic::PLAN_USER_LIMITS['basic'],
            'trial_ends_at' => now()->addDays(30),
            'subscription_status' => 'trial',
            'status' => 'trial',
        ]);

        $adminProfile = Profile::where('slug', 'admin')->first();

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => $data['name'],
            'email' => $data['email'],
            // The User model casts 'password' => 'hashed', so avoid double hashing here.
            'password' => $data['password'],
            'profile_id' => $adminProfile?->id,
        ]);

        // ── Auto-bootstrap booking ──────────────────────────────────
        // The owner is automatically a booking professional with a
        // default schedule so the clinic works in online booking from day 1.
        $this->bootstrapBooking($clinic, $user);
        // ────────────────────────────────────────────────────────────

        $activationUrl = URL::temporarySignedRoute(
            'api.register.activate',
            now()->addHours(24),
            ['user' => $user->id],
            ['url' => config('app.url')]
        );

        Mail::to($user->email)->queue(new AccountActivationMail($user, $activationUrl));

        return response()->json([
            'message' => 'Cuenta creada. Revisa tu correo para activar el periodo de prueba.',
        ], 201);
    }

    private function bootstrapBooking(Clinic $clinic, User $user): void
    {
        // 1. Owner becomes a booking professional with online booking enabled.
        BookingProfessional::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'allow_online_booking' => true,
        ]);

        // 2. Active booking page with a generated slug.
        $slug = Str::slug($clinic->name);
        // Ensure uniqueness: append a short suffix if needed.
        if (BookingPage::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        BookingPage::create([
            'clinic_id' => $clinic->id,
            'slug' => $slug,
            'title' => 'Reserva tu cita',
            'is_active' => true,
            'max_horizon_days' => 60,
            'cancellation_hours' => 24,
        ]);

        // 3. Default schedule: Mon–Fri 09:00–17:00, Sat–Sun disabled.
        //    Uses UserSchedule (0-6 ISO, Sun=0) as the base schedule.
        //    ProfessionalSchedule is created as a copy so the booking
        //    engine uses it directly without the "Usando horario de Equipo" fallback.
        $bp = BookingProfessional::where('user_id', $user->id)->first();

        $defaultSchedule = [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'], // Mon
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00'], // Tue
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00'], // Wed
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00'], // Thu
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00'], // Fri
        ];

        // UserSchedule uses 0-6 (Sun=0, Mon=1…Sat=6)
        foreach ($defaultSchedule as $s) {
            $user->schedules()->create([
                'day_of_week' => $s['day_of_week'],
                'enabled' => true,
                'start_time' => $s['start_time'],
                'end_time' => $s['end_time'],
            ]);

            // ProfessionalSchedule uses 1-7 ISO (Mon=1…Sun=7)
            if ($bp) {
                \Modules\Booking\Models\ProfessionalSchedule::create([
                    'professional_id' => $bp->id,
                    'day_of_week' => $s['day_of_week'],
                    'start_time' => $s['start_time'],
                    'end_time' => $s['end_time'],
                ]);
            }
        }
    }
}
