<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account details changed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { width: 80%; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hello {{ $user->first_name ?? 'Volunteer' }},</h1>
        <p>A Red Cross database administrator has changed the login details of your account (DB-{{ $user->id }}):</p>
        <ul>
            @foreach($changes as $change)
                <li>{{ $change }}</li>
            @endforeach
        </ul>
        <p>This is usually done to help you log in. If you did not ask for this, please contact your branch office.</p>
        <p>Regards,<br>Red Cross Volunteers</p>
    </div>
</body>
</html>
