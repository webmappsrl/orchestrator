<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="x-apple-disable-message-reformatting">
  <title>Ticket da utente non registrato</title>
  <style>
    /* Basic reset */
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; }
    img { border: 0; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; background-color: #f3f4f6; }

    /* Container */
    .wrapper { width: 100%; background-color: #f3f4f6; padding: 24px 8px; box-sizing: border-box; }
    .container { width: 100%; max-width: 640px; margin: 0 auto; }
    .card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .header { padding: 18px 20px; background: #111827; color: #ffffff; }
    .header h1 { margin: 0; font-size: 18px; line-height: 1.3; font-weight: 700; }
    .content { padding: 16px; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 1.6; }
    .meta { margin: 0 0 10px 0; color: #374151; }
    .label { color: #6b7280; font-weight: 600; }
    .box { margin-top: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }

    /* Make forwarded HTML safe-ish in email clients */
    .box * { max-width: 100% !important; }
    .box img { height: auto !important; display: block; }
    .box pre, .box code { white-space: pre-wrap; word-break: break-word; }
    .box table { width: 100% !important; }
    .box a { word-break: break-word; }

    .footer { padding: 14px 20px; color: #6b7280; font-size: 12px; }

    @media (max-width: 480px) {
      .wrapper { padding: 12px 6px; }
      .content { padding: 12px; }
      .header { padding: 14px 16px; }
    }
  </style>
</head>
<body>
  <center style="width:100%; background-color:#f3f4f6;">
    <div class="wrapper">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;">
        <tr>
          <td align="center" valign="top" style="padding:0; Margin:0;">
            <table role="presentation" class="container" width="100%" cellspacing="0" cellpadding="0" border="0" style="Margin:0 auto;">
              <tr>
                <td style="padding:0; Margin:0;">
                  <table role="presentation" class="card" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
              <td class="header">
                <h1>Ticket da utente non registrato</h1>
              </td>
            </tr>
            <tr>
              <td class="content">
                <p class="meta"><span class="label">Mittente:</span> <strong>{{ $userEmail }}</strong></p>
                <p class="meta"><span class="label">Oggetto:</span> <strong>{{ $originalSubject }}</strong></p>

                <div class="box">
                  {!! $originalBody !!}
                </div>
              </td>
            </tr>
            <tr>
              <td class="footer">
                Messaggio generato automaticamente da Orchestrator.
              </td>
            </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </div>
  </center>
</body>
</html>
