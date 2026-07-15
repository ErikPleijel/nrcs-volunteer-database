<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verify Your Email</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #dc2626 !important;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            font-weight: bold;
            text-align: center;
            mso-padding-alt: 0;
        }
        .button:hover {
            background-color: #b91c1c !important;
            color: #ffffff !important;
        }
        .button:visited {
            color: #ffffff !important;
        }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }

        /* Gmail-specific fixes */
        u + .body .button { background-color: #dc2626 !important; color: #ffffff !important; }

        /* Outlook-specific fixes */
        .button[x-apple-data-detectors] { color: #ffffff !important; }
    </style>
</head>
<body class="body">
<div class="container">
    <div class="header">
        <h1>Red Cross Volunteers</h1>
    </div>

    <h2>Hello {{ $user->first_name }}!</h2>

    <p>Thank you for registering with Red Cross Volunteers. To complete your registration, please verify your email address by clicking the button below:</p>

    <p style="text-align: center; margin: 30px 0;">
        <table cellpadding="0" cellspacing="0" style="margin: 30px auto;">
            <tr>
                <td style="border-radius: 5px; background-color: #dc2626;">
                    <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; color: #ffffff; text-decoration: none; font-weight: bold; border-radius: 5px;">
                        Verify Email Address
                    </a>
                </td>
            </tr>
        </table>
    </p>

    <p>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</p>
    <p style="word-break: break-all; color: #666;">{{ $url }}</p>

    <p>If you did not create an account, no further action is required.</p>

    <div class="footer">
        <p>© {{ date('Y') }} Red Cross Volunteers. All rights reserved.</p>
    </div>
</div>
</body>
</html>
