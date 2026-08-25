<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }}</title>
</head>

<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#374151; line-height:1.6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">

                    <tr>
                        <td style="background:#1f2937; padding:24px 28px;">
                            <div style="color:#ffffff; font-size:18px; font-weight:600;">{{ $heading }}</div>
                            <div style="color:#9ca3af; font-size:13px; margin-top:4px;">{{ config('app.name') }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 20px; font-size:15px;">{{ $intro }}</p>

                            @if ($ticket)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                    style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:20px;">
                                    <tr>
                                        <td style="padding:4px 0; font-size:13px; color:#6b7280; width:120px;">{{ __('app.ticket') }}</td>
                                        <td style="padding:4px 0; font-size:14px; font-weight:600; color:#111827;">
                                            {{ $ticket->uuid }} — {{ $ticket->name }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 0; font-size:13px; color:#6b7280;">{{ __('app.project') }}</td>
                                        <td style="padding:4px 0; font-size:14px; color:#111827;">{{ $ticket->project?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 0; font-size:13px; color:#6b7280;">{{ __('app.status') }}</td>
                                        <td style="padding:4px 0; font-size:14px; color:#111827;">{{ $ticket->status?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 0; font-size:13px; color:#6b7280;">{{ __('app.priority') }}</td>
                                        <td style="padding:4px 0; font-size:14px; color:#111827;">{{ $ticket->priority?->name ?? '—' }}</td>
                                    </tr>
                                    @if ($ticket->due_date)
                                        <tr>
                                            <td style="padding:4px 0; font-size:13px; color:#6b7280;">{{ __('app.due_date') }}</td>
                                            <td style="padding:4px 0; font-size:14px; color:#111827;">{{ $ticket->due_date->format('d/m/Y') }}</td>
                                        </tr>
                                    @endif
                                </table>
                            @endif

                            @if (! empty($bodyHtml))
                                @if ($bodyTitle)
                                    <div style="font-size:13px; color:#6b7280; margin-bottom:6px;">{{ $bodyTitle }}</div>
                                @endif
                                <div style="border-left:3px solid #d1d5db; padding:4px 0 4px 14px; margin-bottom:24px; font-size:14px; color:#374151;">
                                    {!! $bodyHtml !!}
                                </div>
                            @endif

                            @if ($url)
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 4px;">
                                    <tr>
                                        <td style="background-color:#2563eb; border-radius:8px;">
                                            <a href="{{ $url }}"
                                                style="display:inline-block; padding:12px 24px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">
                                                {{ __('app.mail_open_ticket') }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:16px 0 0; font-size:12px; color:#9ca3af; word-break:break-all;">{{ $url }}</p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:16px 28px; font-size:12px; color:#9ca3af;">
                            {{ __('app.mail_automatic_footer') }}
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
