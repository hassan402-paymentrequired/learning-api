@php
    $appName = config('app.name', 'Stepra');
    $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
    $supportEmail = config('mail.from.address', 'hello@stepra.app');
    $year = date('Y');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', $appName)</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        @media only screen and (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-body-cell { padding-left: 20px !important; padding-right: 20px !important; }
            .email-header-cell { padding-left: 20px !important; padding-right: 20px !important; }
            .email-footer-cell { padding-left: 20px !important; padding-right: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f7; color: #18181b;">
@if(trim($__env->yieldContent('preheader')))
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; opacity: 0; color: transparent; height: 0; width: 0;">
    @yield('preheader')
</div>
@endif

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f5f5f7;">
    <tr>
        <td align="center" style="padding: 32px 16px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="email-shell" style="max-width: 600px; width: 100%;">

                {{-- Brand header --}}
                <tr>
                    <td class="email-header-cell" align="left" style="padding: 0 32px 20px;">
                        <a href="{{ $frontendUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                            <span style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 700; letter-spacing: -0.03em; color: #18181b;">
                                {{ $appName }}
                            </span>
                        </a>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 2px rgba(24, 24, 27, 0.04);">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="height: 4px; background: linear-gradient(90deg, #18181b 0%, #52525b 100%); font-size: 0; line-height: 0;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td class="email-body-cell" style="padding: 36px 32px 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                    @yield('content')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @if($showFooter ?? true)
                {{-- Footer --}}
                <tr>
                    <td class="email-footer-cell" align="center" style="padding: 24px 32px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                        <p style="margin: 0 0 10px; font-size: 13px; line-height: 20px; color: #71717a;">
                            Questions? Reply to this email or contact
                            <a href="mailto:{{ $supportEmail }}" style="color: #18181b; text-decoration: underline;">{{ $supportEmail }}</a>.
                        </p>
                        <p style="margin: 0 0 10px; font-size: 13px; line-height: 20px; color: #71717a;">
                            <a href="{{ $frontendUrl }}/privacy-policy" target="_blank" style="color: #71717a; text-decoration: underline;">Privacy Policy</a>
                            &nbsp;&middot;&nbsp;
                            <a href="{{ $frontendUrl }}" target="_blank" style="color: #71717a; text-decoration: underline;">Open {{ $appName }}</a>
                        </p>
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #a1a1aa;">
                            &copy; {{ $year }} {{ $appName }}. All rights reserved.
                        </p>
                    </td>
                </tr>
                @endif

            </table>
        </td>
    </tr>
</table>
</body>
</html>
