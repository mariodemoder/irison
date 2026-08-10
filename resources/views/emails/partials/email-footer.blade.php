{{-- Pie estandar de emails emitidos por Irison hacia suscriptores.
     Usa config('mail.from.name'/'mail.from.address') y config('app.url'). --}}
<tr>
    <td style="padding:20px 26px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;text-align:center;line-height:1.8;">
        © {{ date('Y') }} {{ config('mail.from.name', 'Irison') }}. Todos los derechos reservados.<br>
        <a href="mailto:{{ config('mail.from.address') }}" style="color:#1d4ed8;text-decoration:none;">{{ config('mail.from.address') }}</a>
        &middot;
        <a href="{{ config('app.url') }}" style="color:#1d4ed8;text-decoration:none;">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a><br>
        <span style="color:#94a3b8;">Este correo es informativo sobre el estado de tu suscripción con Irison.</span>
    </td>
</tr>
