@extends('emails.layouts.irison')

@section('title', 'Activar cuenta')

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Activa tu cuenta</h1>
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $name }},</p>
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">Tu cuenta en <strong>Irison</strong> ya fue creada. Para iniciar tu periodo de prueba, confirma tu email haciendo clic en este botón:</p>
            @include('emails.partials.email-cta', ['url' => $activationUrl, 'label' => 'Activar cuenta e iniciar trial'])
            <p style="margin:20px 0 8px;font-size:13px;color:#64748b;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style="margin:0;word-break:break-all;font-size:13px;color:#1e293b;">{{ $activationUrl }}</p>
            <p style="margin:12px 0 0;font-size:13px;color:#64748b;">Este enlace vence en 24 horas.</p>
        </td>
    </tr>
@endsection
