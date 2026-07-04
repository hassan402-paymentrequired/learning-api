@props([
    'type' => 'info',
])

@php
    $styles = match ($type) {
        'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e'],
        'success' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'text' => '#166534'],
        default => ['bg' => '#f4f4f5', 'border' => '#e4e4e7', 'text' => '#52525b'],
    };
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 24px;">
    <tr>
        <td style="background-color: {{ $styles['bg'] }}; border: 1px solid {{ $styles['border'] }}; border-radius: 12px; padding: 14px 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 22px; color: {{ $styles['text'] }};">
            {{ $slot }}
        </td>
    </tr>
</table>
