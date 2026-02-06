<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <style type="text/css">
    @media only screen and (min-width: 620px) {
      .u-row { width: 600px !important; }
      .u-row .u-col { vertical-align: top; }
      .u-row .u-col-100 { width: 600px !important; }
    }

    @media only screen and (max-width: 620px) {
      .u-row-container { max-width: 100% !important; padding-left: 0px !important; padding-right: 0px !important; }
      .u-row { width: 100% !important; }
      .u-row .u-col { display: block !important; width: 100% !important; min-width: 320px !important; max-width: 100% !important; }
      .u-row .u-col > div { margin: 0 auto; }
    }

    body{margin:0;padding:0}table,td,tr{border-collapse:collapse;vertical-align:top}.ie-container table,.mso-container table{table-layout:fixed}*{line-height:inherit}a[x-apple-data-detectors=true]{color:inherit!important;text-decoration:none!important}
    table, td { color: #000000; } #u_body a { color: #0000ee; text-decoration: underline; } @media (max-width: 480px) { #u_content_button_1 .v-size-width { width: 76% !important; } }
  </style>

  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet" type="text/css">
</head>

<body class="clean-body u_body" style="margin: 0;padding: 0;-webkit-text-size-adjust: 100%;background-color: #f0f0f0;color: #000000">

<table role="presentation" id="u_body" style="border-collapse: collapse;table-layout: fixed;border-spacing: 0;mso-table-lspace: 0pt;mso-table-rspace: 0pt;vertical-align: top;min-width: 320px;Margin: 0 auto;background-color: #f0f0f0;width:100%" cellpadding="0" cellspacing="0">
  <tbody>
  <tr style="vertical-align: top">
    <td style="word-break: break-word;border-collapse: collapse !important;vertical-align: top">

      {{-- Header with logo --}}
      <div class="u-row-container" style="padding: 0px;background-color: transparent">
        <div class="u-row" style="margin: 0 auto;min-width: 320px;max-width: 600px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;">
          <div style="border-collapse: collapse;display: table;width: 100%;height: 100%;background-color: transparent;">
            <div class="u-col u-col-100" style="max-width: 320px;min-width: 600px;display: table-cell;vertical-align: top;">
              <div style="background-color: #ddffe7;height: 100%;width: 100% !important;">
                <div style="box-sizing: border-box; height: 100%; padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;">
                  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                    <tbody>
                      <tr>
                        <td style="overflow-wrap:break-word;word-break:break-word;padding:10px;font-family:arial,helvetica,sans-serif;" align="left">
                          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                              <td style="padding-right: 0px;padding-left: 0px;" align="center">
                                <img align="center" border="0" src="{{ $logoUrl ?? asset('images/image-1.png') }}" alt="{{ config('app.name') }}" title="{{ config('app.name') }}" style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: inline-block !important;border: none;height: auto;float: none;width: 100%;max-width: 190px;" width="190"/>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Main content --}}
      <div class="u-row-container" style="padding: 0px;background-color: transparent">
        <div class="u-row" style="margin: 0 auto;min-width: 320px;max-width: 600px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;">
          <div style="border-collapse: collapse;display: table;width: 100%;height: 100%;background-color: transparent;">
            <div class="u-col u-col-100" style="max-width: 320px;min-width: 600px;display: table-cell;vertical-align: top;">
              <div style="background-color: #ffffff;height: 100%;width: 100% !important;border-radius: 0px;-webkit-border-radius: 0px; -moz-border-radius: 0px;">
                <div style="box-sizing: border-box; height: 100%; padding: 0px;border-top: 0px solid transparent;border-left: 0px solid transparent;border-right: 0px solid transparent;border-bottom: 0px solid transparent;border-radius: 0px;-webkit-border-radius: 0px; -moz-border-radius: 0px;">
                  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                    <tbody>
                      <tr>
                        <td style="overflow-wrap:break-word;word-break:break-word;padding:30px 10px 10px;font-family:arial,helvetica,sans-serif;" align="left">
                          @yield('content')
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      @if($showFooter ?? true)
      {{-- Footer --}}
      <div class="u-row-container" style="padding: 2px 0px 0px;background-color: transparent">
        <div class="u-row" style="margin: 0 auto;min-width: 320px;max-width: 600px;overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;">
          <div style="border-collapse: collapse;display: table;width: 100%;height: 100%;background-color: transparent;">
            <div class="u-col u-col-100" style="max-width: 320px;min-width: 600px;display: table-cell;vertical-align: top;">
              <div style="background-color: #ffffff;height: 100%;width: 100% !important;border-radius: 0px;-webkit-border-radius: 0px; -moz-border-radius: 0px;">
                <div style="box-sizing: border-box; height: 100%; padding: 0px;">
                  <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                    <tbody>
                      <tr>
                        <td style="overflow-wrap:break-word;word-break:break-word;padding:30px 10px 10px;font-family:arial,helvetica,sans-serif;" align="left">
                          <h1 style="margin: 0px; line-height: 140%; text-align: center; word-wrap: break-word; font-family: 'Montserrat',sans-serif; font-size: 13px; font-weight: 400;">
                            <span>If you have any questions, contact our Website Guides. Or, visit our Help Center.</span>
                          </h1>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif

    </td>
  </tr>
  </tbody>
</table>
</body>
</html>
