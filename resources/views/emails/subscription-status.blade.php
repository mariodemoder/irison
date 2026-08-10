@extends('emails.layouts.irison')

@section('title', 'Solicitud de upgrade ' . $status)

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Solicitud de upgrade {{ $status }}</h1>
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">La solicitud de cambio de plan de <strong>{{ $currentPlan }}</strong> a <strong>{{ $requestedPlan }}</strong> para <strong>{{ $clinicName }}</strong> ha sido <strong>{{ $status }}</strong>.</p>
            @if ($comments !== '-')
                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;"><strong>Comentarios:</strong> {{ $comments }}</p>
            @endif
            @if(!empty($invoiceUrl))
                @include('emails.partials.email-cta', ['url' => $invoiceUrl, 'label' => 'Ver factura'])
            @endif
        </td>
    </tr>
@endsection
