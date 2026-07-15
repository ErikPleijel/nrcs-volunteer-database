<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Certificates</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;500&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }

        .certificate-container {
            width: 297mm;
            height: 210mm;
            margin: 20px auto;
            padding: 40px;
            box-sizing: border-box;
            background-color: white;
            /* Old border removed, SVG frame used instead */
            /* border: 10px double #ED1B2E; */
            position: relative;
            page-break-after: always;
            display: block;
        }

        .certificate-container:last-child {
            page-break-after: auto;
        }

        /* SVG frame sits behind everything */
        .certificate-frame {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        /* Actual content sits above frame and uses flex layout */
        .certificate-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-start; /* stack items at the top */
        }

        .header {
            text-align: center;
        }

        h1 {
            margin-top: 0;
        }

        .logo {
            width: 100px;
            margin-bottom: 0px;
        }

        .org-name {
            font-size: 32px;
            font-weight: 500;
            color: #ED1B2E; /* Red Cross Red */
            margin-top: 0;
            margin-bottom: 0px; /* Reduced space below the organization name */
        }

        /* New style for the Certificate of Competence image */
        .certificate-competence-image {
            display: block;
            margin-top: 0px; /* Space above the image */
            margin-bottom: 0px; /* Space below the image */
            margin-left: auto; /* Centering */
            margin-right: auto; /* Centering */
            width: 90%; /* Stretches to 90% of its parent container's width */
            max-width: 90%; /* Ensures it doesn't exceed 90% if its natural size is larger */
            height: auto; /* Maintains aspect ratio */
        }

        .main-content {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-start; /* instead of center */
            flex-grow: 0;                /* optional, stops it from stretching */
        }

        .certify-text {
            font-size: 18px;
            margin-bottom: 10px;
            margin-top: 0; /* Ensures no default top margin pushes it away from the image */
        }

        .recipient-name {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            font-weight: 700;
            color: #333;
            margin: 0 0;
        }

        .course-title-text {
            font-size: 18px;
            margin-bottom: 0px;
        }

        .course-title {
            font-size: 28px;
            font-weight: 500;
            color: #333;
        }

        .training-details {
            font-size: 16px;
            margin-top: 0;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 0px;
            margin-top: auto; /* keeps footer on bottom of page */
            gap: 20px;        /* space between left / signatures / right */
        }

        /* Left side: small meta text */
        .footer-left {
            flex: 0 0 auto;
            padding-left: 10mm;     /* pushes it away from left red frame */
            transform: translateY(-6mm); /* lifts it up visually */
        }
        .certificate-info {
            font-size: 10px;
            color: #777;
            text-align: left;
        }

        /* Center: signatures nicely centered, not pushed to the side */
        .footer-signatures {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;   /* center signatures */
            gap: 40px;                 /* space between signature blocks */
        }

        /* Right: QR slot (real or placeholder) */
        .footer-right {
            flex: 0 0 auto;
            padding-right: 10mm;    /* pushes it away from right red frame */
            transform: translateY(-6mm); /* lifts it up visually */
        }

        .qr-code {
            width: 32mm;
            height: 32mm;
            object-fit: contain;
        }

        .qr-placeholder {
            width: 25mm;
            height: 25mm;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            color: #555;
            box-sizing: border-box;
        }

        /* Signature block stays mostly as you had it */
        .signature-block {
            text-align: center;
            width: 45%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 6mm;
        }

        .sign-above-line {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            margin-bottom: 3px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 0;
            margin-bottom: 3px;
        }

        .signature-title {
            font-size: 13px;
            font-weight: 500;
            margin-top: 1px;
            margin-bottom: 0;
        }

        @media print {
            body {
                background-color: white;
            }
            .certificate-container {
                margin: 0;
                border: none;
                box-shadow: none;
                width: 100%;
                height: 100vh;
            }
        }
    </style>
</head>
<body>

@if(isset($certificates) && count($certificates) > 0)
    @foreach($certificates as $certificate)
        @php
            $user     = $certificate['user']     ?? null;
            $training = $certificate['training'] ?? null;

            // Default type
            $certificateType = $certificate['certificate_type'] ?? 'training_competence';

            $verificationUrl = null;
            $qrBase64        = null;

            if ($user && !empty($user->id_check_token)) {

                $params = [
                    'u'    => $user->id_check_token,
                    'type' => $certificateType,
                ];

                if ($training && !empty($training->id)) {
                    $params['training_id'] = $training->id;
                }

                $verificationUrl = \Illuminate\Support\Facades\URL::signedRoute(
                    'certificates.verify',
                    $params
                );

                // Generate as SVG (no Imagick required)
                $qrBase64 = base64_encode(
                    \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                        ->size(120)
                        ->margin(1)
                        ->generate($verificationUrl)
                );
            }
        @endphp


        <div class="certificate-container">

            {{-- Triple red SVG frame --}}
            <svg class="certificate-frame"
                 xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 100 100"
                 preserveAspectRatio="none">

                <!-- Outer very thin frame -->
                <rect x="2" y="2"
                      width="96" height="96"
                      fill="none"
                      stroke="#ED1B2E"
                      stroke-width="0.4" />

                <!-- Middle medium frame -->
                <rect x="3.3" y="3.3"
                      width="93.4" height="93.4"
                      fill="none"
                      stroke="#ED1B2E"
                      stroke-width="1.1" />

                <!-- Inner very thin frame -->
                <rect x="4.6" y="4.6"
                      width="90.8" height="90.8"
                      fill="none"
                      stroke="#ED1B2E"
                      stroke-width="0.4" />
            </svg>

            <img src="{{ asset('images/NRCS_logo.jpg') }}" alt="" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 150mm; height: auto; opacity: 0.06; pointer-events: none; z-index: 0;">

            <div class="certificate-content">
                <header class="header">
                    @if(isset($certificate['logoUrl']))
                        <img src="{{ $certificate['logoUrl'] }}" alt="Logo" class="logo">
                    @endif
                    <h1 class="org-name">{{ $certificate['orgName'] ?? 'Organisation Name' }}</h1>
                    {{-- Certificate image --}}
                    @if(isset($certificate['certificateImageUrl']))
                        <img src="{{ $certificate['certificateImageUrl'] }}" alt="Certificate Background" class="certificate-competence-image">
                    @endif
                </header>

                <main class="main-content">
                    <p class="certify-text">This is to certify that</p>
                    <h2 class="recipient-name">{{ $certificate['recipientName'] ?? 'Recipient Name' }}</h2>
                    <p class="course-title-text">{{ $certificate['certifyText'] ?? 'has successfully completed the training course on' }}</p>
                    <h3 class="course-title">{{ $certificate['courseTitle'] ?? 'Course Title' }}</h3>
                    <p class="training-details">
                        {{ $certificate['dateLine'] ?? '' }}
                    </p>
                    @if(!empty($certificate['validLine']))
                        <p class="training-details" style="margin-top: 4px;">
                            {{ $certificate['validLine'] }}
                        </p>
                    @endif
                </main>

                <footer class="footer">
                    <div class="footer-left">
                        <div class="certificate-info">
                            Ref: {{ $certificate['training']->training_reference ?? ($certificate['payment']->payment_reference ?? '—') }}<br>
                            Printed by: {{ $certificate['footerProducer'] ?? 'System' }} on {{ now()->format('Y-m-d') }}
                        </div>
                    </div>

                    <div class="footer-signatures">
                        @for ($i = 0; $i < ($certificate['signaturesCount'] ?? 2); $i++)
                            @php $signNum = $i + 1; @endphp
                            <div class="signature-block">
                                <div class="sign-above-line">
                                    @if (!empty($certificate['sign' . $signNum . 'Image']))
                                        <img src="{{ $certificate['sign' . $signNum . 'Image'] }}" alt="" style="max-height:36px; border:none;">
                                    @endif
                                    @if (!empty($certificate['sign' . $signNum . 'Name']))
                                        <span style="font-size:11px;">{{ $certificate['sign' . $signNum . 'Name'] }}</span>
                                    @endif
                                </div>
                                <div class="signature-line"></div>
                                <p class="signature-title">{{ $certificate['defaultSign' . ($i + 1)] ?? 'Signature Title' }}</p>
                            </div>
                        @endfor
                    </div>

                    <div class="footer-right">
                        @if (!empty($certificate['qrCodeUrl']))
                            {{-- If controller provided an explicit QR URL, use that --}}
                            <img src="{{ $certificate['qrCodeUrl'] }}" alt="Verification QR code" class="qr-code">
                        @elseif (!empty($qrBase64))
                            {{-- Auto-generated QR from signed verification URL --}}
                            <img src="data:image/svg+xml;base64, {{ $qrBase64 }}" alt="Verification QR code" class="qr-code">

                        @else
                            <div class="qr-placeholder">
                                QR
                            </div>
                        @endif
                    </div>

                </footer>
            </div>
        </div>
    @endforeach
@else
    <div style="padding: 40px; text-align: center;">
        <h2>No Certificate Data</h2>
        <p>No data was provided to generate the certificate(s).</p>
        <p>Please <a href="{{ route('certificates.index') }}">go back</a> and select training records.</p>
    </div>
@endif

</body>
</html>
