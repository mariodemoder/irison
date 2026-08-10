@extends('emails.layouts.irison')

@section('title', 'Solicitud de reactivación ' . $status)

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Solicitud de reactivación {{ $status }}</h1>
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">Tu solicitud de reactivación de la cuenta de <strong>{{ $clinicName }}</strong> ha sido <strong>{{ $status }}</strong>.</p>
            @if ($comments !== '-')
                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;"><strong>Comentarios:</strong> {{ $comments }}</p>
            @endif
            @if ($status === 'aprobada')
                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">El equipo de Irison se pondrá en contacto contigo para completar el proceso de reactivación.</p>
            @endif
        </td>
    </tr>
@endsection
