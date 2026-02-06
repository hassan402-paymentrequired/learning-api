@extends('emails.layouts.base')

@section('content')
<!--[if mso]><table role="presentation" width="100%"><tr><td><![endif]-->
<h1 style="margin: 0px; line-height: 140%; text-align: center; word-wrap: break-word; font-family: 'Montserrat',sans-serif; font-size: 22px; font-weight: 700;"><span>Your one-time code is</span></h1>
<!--[if mso]></td></tr></table><![endif]-->

<table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
  <tbody>
    <tr>
      <td style="overflow-wrap:break-word;word-break:break-word;padding:10px;font-family:arial,helvetica,sans-serif;" align="left">
        <div align="center">
          <div class="v-button v-size-width" style="box-sizing: border-box; display: inline-block; text-decoration: none; text-size-adjust: none; text-align: center; color: rgb(0, 0, 0); background: rgb(255, 255, 255); border-radius: 0px; width: 38%; max-width: 100%; word-break: break-word; overflow-wrap: break-word; border-color: rgb(0, 0, 0); border-style: solid; border-width: 2px; font-size: 18px; line-height: inherit;"><span style="display:block;padding:10px 20px;line-height:120%;">{{ $otp }}</span></div>
        </div>
      </td>
    </tr>
  </tbody>
</table>

<table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
  <tbody>
    <tr>
      <td style="overflow-wrap:break-word;word-break:break-word;padding:30px 10px 10px;font-family:arial,helvetica,sans-serif;" align="left">
        <div style="font-size: 14px; line-height: 140%; text-align: center; word-wrap: break-word;">
          <p style="line-height: 140%; margin: 0px;">Please verify you're really you by entering this 6-digit code when you sign in.</p>
          <p style="line-height: 140%; margin: 0px;">Just a heads up, this code will expire in 20 minutes for security reasons.</p>
        </div>
      </td>
    </tr>
  </tbody>
</table>

<!--[if mso]><table role="presentation" width="100%"><tr><td><![endif]-->
<h1 style="margin: 0px; line-height: 140%; text-align: center; word-wrap: break-word; font-family: 'Montserrat',sans-serif; font-size: 16px; font-weight: 400;"><span>If you didn't just try to sign in,<br />we recommend you reset your password here:</span></h1>
<!--[if mso]></td></tr></table><![endif]-->

<table id="u_content_button_1" style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
  <tbody>
    <tr>
      <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 10px 30px;font-family:arial,helvetica,sans-serif;" align="left">
        <div align="center">
          <a href="{{ config('app.frontend_url', config('app.url')) }}/authenticate/reset-password" target="_blank" class="v-button v-size-width" style="box-sizing: border-box; display: inline-block; text-decoration: none; text-size-adjust: none; text-align: center; color: rgb(255, 255, 255); background: rgb(0, 0, 0); border-radius: 0px; width: 48%; max-width: 100%; word-break: break-word; overflow-wrap: break-word; border-color: rgb(0, 0, 0); border-style: solid; border-width: 2px; font-size: 18px; line-height: inherit;"><span style="display:block;padding:10px 20px 8px;line-height:120%;">Reset Your Password</span></a>
        </div>
      </td>
    </tr>
  </tbody>
</table>
@endsection
