<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body'    => ['required', 'string', 'max:4000'],
        ]);

        $user   = $request->user();
        $clinic = $user->clinic;

        Mail::to(env('CONTACT_EMAIL', 'hola@irison.es'))->queue(new ContactMail(
            clinicId:       $clinic->id,
            clinicName:     $clinic->name,
            senderName:     $user->name,
            senderEmail:    $user->email,
            contactSubject: $data['subject'],
            body:           $data['body'],
        ));

        return response()->json(['message' => 'Mensaje enviado correctamente.']);
    }
}
