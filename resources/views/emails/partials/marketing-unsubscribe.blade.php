@props([
    'unsubscribeUrl',
    'preferencesUrl',
])

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top: 28px;">
    <tr>
        <td style="border-top: 1px solid #e4e4e7; padding-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 18px; color: #a1a1aa; text-align: center;">
            You're receiving this because you opted in to product updates and tips.
            <a href="{{ $preferencesUrl }}" target="_blank" style="color: #71717a; text-decoration: underline;">Manage preferences</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ $unsubscribeUrl }}" target="_blank" style="color: #71717a; text-decoration: underline;">Unsubscribe</a>
        </td>
    </tr>
</table>
