{{-- Boton CTA estandar (azul primario de la app) --}}
@props(['url', 'label'])
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:20px 0 0;">
    <tr>
        <td style="background:linear-gradient(135deg,#0ea5e9,#1d4ed8);border-radius:10px;">
            <a href="{{ $url }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">{{ $label }}</a>
        </td>
    </tr>
</table>
