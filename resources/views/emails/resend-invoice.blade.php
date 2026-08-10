@extends('emails.layouts.irison')

@section('title', 'Factura de Irison')

@section('content')
    <tr>
        <td style="padding:24px 26px;">
            <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#020617;">Tu factura</h1>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">Hola {{ $clinicName }}, {{ $message }}</p>
            @include('emails.partials.email-cta', ['url' => $invoiceUrl, 'label' => 'Ver factura'])
        </td>
    </tr>
@endsection
