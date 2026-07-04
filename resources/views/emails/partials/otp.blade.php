@props([
    'code',
    'expires' => null,
])

@php
    $digits = preg_replace('/\D/', '', (string) $code);
    $formatted = strlen($digits) === 6
        ? substr($digits, 0, 3) . ' ' . substr($digits, 3)
        : $code;
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
    <tr>
        <td align="center" style="padding: 8px 0 4px;">
            <div style="display: inline-block; background-color: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 14px; padding: 20px 32px;">
                <span style="font-family: 'SF Mono', SFMono-Regular, ui-monospace, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 34px; font-weight: 700; letter-spacing: 0.28em; color: #18181b; line-height: 1;">
                    {{ $formatted }}
                </span>
            </div>
        </td>
    </tr>
    @if($expires)
        <tr>
            <td align="center" style="padding: 12px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 20px; color: #71717a;">
                Expires in {{ $expires }}
            </td>
        </tr>
    @endif
</table>
