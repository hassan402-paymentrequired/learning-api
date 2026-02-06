@extends('emails.layouts.base')

@section('content')
<!--[if mso]><table role="presentation" width="100%"><tr><td><![endif]-->
<h1 style="margin: 0px; line-height: 140%; text-align: center; word-wrap: break-word; font-family: 'Montserrat',sans-serif; font-size: 22px; font-weight: 700;"><span>Welcome to {{ config('app.name') }}!</span></h1>
<!--[if mso]></td></tr></table><![endif]-->

<table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
  <tbody>
    <tr>
      <td style="overflow-wrap:break-word;word-break:break-word;padding:20px 10px;font-family:arial,helvetica,sans-serif;" align="left">
        <div style="font-size: 14px; line-height: 140%; text-align: center; word-wrap: break-word;">
          <p style="line-height: 140%; margin: 0px;">Hi {{ $user->name ?? 'there' }},</p>
          <p style="line-height: 140%; margin: 10px 0 0 0;">Thank you for signing up. Your email has been verified and your account is now active.</p>
          <p style="line-height: 140%; margin: 10px 0 0 0;">You can now sign in and start exploring.</p>
        </div>
      </td>
    </tr>
  </tbody>
</table>

<table id="u_content_button_1" style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
  <tbody>
    <tr>
      <td style="overflow-wrap:break-word;word-break:break-word;padding:10px 10px 30px;font-family:arial,helvetica,sans-serif;" align="left">
        <div align="center">
          <a href="{{ config('app.frontend_url', config('app.url')) }}/authenticate/login" target="_blank" class="v-button v-size-width" style="box-sizing: border-box; display: inline-block; text-decoration: none; text-size-adjust: none; text-align: center; color: rgb(255, 255, 255); background: rgb(0, 0, 0); border-radius: 0px; width: 48%; max-width: 100%; word-break: break-word; overflow-wrap: break-word; border-color: rgb(0, 0, 0); border-style: solid; border-width: 2px; font-size: 18px; line-height: inherit;"><span style="display:block;padding:10px 20px 8px;line-height:120%;">Sign In</span></a>
        </div>
      </td>
    </tr>
  </tbody>
</table>
@endsection
