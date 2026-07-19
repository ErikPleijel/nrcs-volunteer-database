<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Verification</title>

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

        .ok {
            color: #0c7b27;
        }

        .error {
            color: #b30000;
        }

        .section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .label {
            font-size: 12px;
            color: #777;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .info-table {
            width: 100%;
            margin-top: 10px;
        }

        .info-table td {
            padding: 6px 0;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }

        .rc-logo {
            width: 120px;
            display: block;
            margin: 0 auto 10px auto;
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

        {{-- Success --}}
        @if($valid && $user)
            <div class="title ok">Certificate Verified</div>

            <p style="text-align:center; color:#444; margin-top:-5px;">
                This certificate belongs to a person who is registered in the<br>
                <strong>Nigeria Red Cross Society</strong> database.
            </p>

            <div class="section">
                <div class="label">Holder</div>
                <div class="value">{{ $user->full_name }}</div>
                <div class="label">ID</div>
                <div class="value">{{ $user->user_id_reference_short ?? $user->id }}</div>
            </div>
            <div class="section">
                <div class="label">Certificate Type</div>
                <div class="value" style="text-transform: capitalize;">
                    {{ str_replace('_', ' ', $certificate['type']) }}
                </div>
            </div>

            @if($certificate['training'])
                <div class="section">

                    <table class="info-table">
                        <tr>
                            <td class="label">Course</td>
                            <td class="value">{{ $certificate['training']->trainingType->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Training ID</td>
                            <td class="value">{{ $certificate['training']->id }}</td>
                        </tr>
                        <tr>
                            <td class="label">Date</td>
                            <td class="value">
                                {{ $certificate['training']->training_date ? \Carbon\Carbon::parse($certificate['training']->training_date)->format('d M Y') : 'N/A' }}
                            </td>
                        </tr>
                    </table>
                </div>
            @endif

            <div class="section">
                <div class="label">Branch / Division</div>
                <div class="value">
                    {{ $user->branch->name ?? 'N/A' }} /
                    {{ $user->division->name ?? 'N/A' }}
                </div>
            </div>

            {{-- Invalid or tampered link --}}
        @else
            <div class="title error">Verification Failed</div>

            <p style="text-align:center; color:#444; margin-top:-5px;">
                This verification link is invalid or has been tampered with.
            </p>

            @if($reason === 'invalid_parameters')
                <p style="text-align:center; color:#888;">Invalid certificate parameters.</p>
            @elseif($reason === 'user_not_found')
                <p style="text-align:center; color:#888;">User not found in the Red Cross system.</p>
            @endif
        @endif

        <div class="footer-note">
            If the printed details on the certificate do not match the information shown here,
            please contact your local Branch of the Nigeria Red Cross Society.
        </div>

    </div>
</div>

</body>
</html>
