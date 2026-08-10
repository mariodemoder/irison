<?php

namespace App\Mail;

use App\Models\PatientConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsentSignRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PatientConsent $consent,
        public readonly string $signUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Firma de consentimiento - ' . ($this->consent->clinic->name ?? 'Irison'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $patientName = $this->consent->patient->first_name ?? 'Paciente';
        $clinicName = $this->consent->clinic->name ?? 'Irison';
        $templateTitle = $this->consent->template->title ?? 'consentimiento';

        $headerHtml = view('emails.partials.email-clinic-header', ['clinic' => $this->consent->clinic])->render();

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; margin:0; padding:24px; color: #1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        {$headerHtml}
        <tr>
            <td style="padding: 24px;">
                <h2>Hola {$patientName}</h2>
                <p>{$clinicName} te ha solicitado la firma de un consentimiento:</p>
                <p style="font-weight: 600;">{$templateTitle}</p>
                <p style="margin: 24px 0;">
                    <a href="{$this->signUrl}"
                       style="display: inline-block; padding: 12px 24px; background: #4338ca; color: #fff;
                              text-decoration: none; border-radius: 8px; font-weight: 600;">
                        Firmar consentimiento
                    </a>
                </p>
                <p style="color: #6b7280; font-size: 14px;">
                    Este enlace caduca en 72 horas.
                </p>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">
                <p style="color: #9ca3af; font-size: 12px;">{$clinicName} — Software de gestión clínica</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
