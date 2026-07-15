<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { width: 80%; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .button { display: inline-block; background-color: #dc3545; color: #ffffff !important; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hello {{ $user->name ?? 'User' }},</h1>
        <p>You are receiving this email because we received a password reset request for your account.</p>
        <p>Please click the button below to reset your password:</p>
        <p>
            <a href="{{ $url }}" class="button">Reset Password</a>
        </p>
        <p>This password reset link will expire in {{ $count }} minutes.</p>
        <p>If you did not request a password reset, no further action is required.</p>
        <p>Regards,<br>Red Cross Volunteers</p>
    </div>
</body>
</html>
