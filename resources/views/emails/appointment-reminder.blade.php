<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background-color:#fafafa;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#fafafa;">
        <tr>
            <td align="center" style="padding:24px 0;">
                <table width="570" cellpadding="0" cellspacing="0" role="presentation" style="width:570px;max-width:570px;background-color:#ffffff;border:1px solid #e4e4e7;border-radius:4px;">
                    @include('emails.partials.email-clinic-header', ['clinic' => $clinic ?? null])

                    <tr>
                        <td style="padding:32px;color:#52525b;font-size:16px;line-height:1.5;">
                            <p>Hola {{ $patientName }},</p>

                            <p>Te recordamos que tienes una cita en {{ $hoursBefore }} horas.</p>

                            <p>
                                <strong>Clinica:</strong> {{ $clinicName }}<br>
                                <strong>Fecha:</strong> {{ $dateText }}<br>
                                <strong>Hora:</strong> {{ $timeText }}
                            </p>

                            <p>Si necesitas reprogramar, por favor contacta con la clinica.</p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:16px 24px;color:#a1a1aa;font-size:12px;">
                            © {{ date('Y') }} {{ isset($clinic) && $clinic ? $clinic->name : config('app.name') }}. Todos los derechos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
