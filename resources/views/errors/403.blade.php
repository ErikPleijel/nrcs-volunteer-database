<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .title {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }

        .error {
            color: #b30000;
        }

        .message {
            text-align: center;
            color: #444;
            margin-top: -5px;
        }

        .rc-logo {
            width: 120px;
            display: block;
            margin: 0 auto 10px auto;
        }

        .home-link-wrap {
            text-align: center;
            margin-top: 25px;
        }

        .home-link {
            display: inline-block;
            background: #b30000;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 22px;
            border-radius: 6px;
        }

        .home-link:hover {
            background: #8f0000;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="card">

        {{-- Red Cross logo --}}
        <img src="{{ asset('images/NRCS_logo.jpg') }}"
             alt="NRCS Logo"
             class="rc-logo">

        <div class="title error">Access Denied</div>

        <p class="message">
            You don't have permission to view this page.<br>
            If you believe this is a mistake, please contact your administrator.
        </p>

        <div class="home-link-wrap">
            <a href="{{ route('welcome') }}" class="home-link">Return to Home</a>
        </div>

        <div class="footer-note">
            Nigeria Red Cross Society — Volunteer Database
        </div>

    </div>
</div>

</body>
</html>
