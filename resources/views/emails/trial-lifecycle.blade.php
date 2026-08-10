@extends('emails.layouts.irison')

@section('title', $headline)

@section('content')
    <tr>
        <td style="padding:24px 26px 0 26px;">
            <p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Irison Trial · {{ strtoupper($milestone) }}</p>
            <h1 style="margin:8px 0 0 0;font-size:22px;line-height:1.3;color:#020617;">{{ $headline }}</h1>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 26px 0 26px;font-size:15px;line-height:1.6;">
            <p style="margin:0 0 10px 0;color:#334155;">Hola, equipo de {{ $clinicName }}.</p>
            <p style="margin:0 0 12px 0;color:#334155;">{{ $messageBody }}</p>
            @if($trialEndsAt)
                <p style="margin:0 0 12px 0;color:#334155;"><strong>Fin estimado del trial:</strong> {{ \Illuminate\Support\Carbon::parse($trialEndsAt)->format('d/m/Y H:i') }}</p>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:8px 26px 24px 26px;">
            @include('emails.partials.email-cta', ['url' => $billingUrl, 'label' => 'Ver opciones de conversión'])
        </td>
    </tr>
@endsection
