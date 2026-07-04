@props([
    'url',
    'label',
    'align' => 'center',
])

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
    <tr>
        <td align="{{ $align }}" style="padding: 0;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:220px;" arcsize="16%" strokecolor="#18181b" fillcolor="#18181b">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:600;">{{ $label }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ $url }}"
               target="_blank"
               style="display: inline-block; background-color: #18181b; color: #ffffff !important; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; line-height: 48px; text-align: center; text-decoration: none; border-radius: 10px; padding: 0 28px; min-width: 180px; mso-hide: all;">
                {{ $label }}
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
