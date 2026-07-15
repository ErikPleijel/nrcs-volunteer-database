<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Certificates – Portrait</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;500&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }

        /* Portrait A4: 210mm wide, 297mm high */
        .certificate-container {
            width: 210mm;
            height: 297mm;
            margin: 20px auto;
            padding: 40px;
            box-sizing: border-box;
            background-color: white;
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

        /* Content uses flex column layout */
        .certificate-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .header {
            text-align: center;
        }

        h1 {
            margin-top: 0;
        }

        .logo {
            width: 90px;
            margin-bottom: 0;
            margin-top: 15px;
        }

        .org-name {
            font-size: 30px;
            font-weight: 500;
            color: #ED1B2E; /* Red Cross Red */
            margin-top: 0;
            margin-bottom: 4px;
        }

        /* Certificate of Competence / heading image */
        .certificate-competence-image {
            display: block;
            margin-top: 0;
            margin-bottom: 8px;
            margin-left: auto;
            margin-right: auto;
            width: 85%;
            max-width: 85%;
            height: auto;
        }

        .main-content {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            flex-grow: 1; /* let main content take available vertical space */
            padding: 10mm 15mm 0 15mm; /* slightly narrower column */
        }

        .certify-text {
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .recipient-name {
            font-family: 'Playfair Display', serif;
            font-size: 44px;
            font-weight: 700;
            color: #333;
            margin: 0 0 26px 0;
        }

        .course-title-text {
            font-size: 18px;
            margin-bottom: 24px;
            margin-top: 0;
        }

        .course-title {
            font-size: 26px;
            font-weight: 500;
            color: #333;
            margin: 0 0 8px 0;
        }

        .training-details {
            font-size: 16px;
            margin-top: 10px;
        }

        /* Table for donation/volunteering items */
        .items-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
            font-size: 12px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: 500;
            text-align: center;
        }
        .items-table td.number,
        .items-table td.date,
        .items-table td.hours,
        .items-table td.amount {
            text-align: center;
        }
        .items-table td.activity,
        .items-table td.description {
            text-align: left;
        }
        .items-table tfoot .total-row td {
            border-top: 2px solid #333;
            font-weight: bold;
        }

        /* Footer pinned to bottom */
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 0;
            margin-top: auto;
            gap: 20px;
        }

        /* Left: small meta text */
        .footer-left {
            flex: 0 0 auto;
            padding-left: 10mm;           /* keep off the red frame */
            transform: translateY(-6mm);  /* lift slightly above inner frame */
        }

        .certificate-info {
            font-size: 10px;
            color: #777;
            text-align: left;
            width: 32mm;
            position: relative;
            left: -20px;
        }

        /* Center: signatures centered */
        .footer-signatures {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            gap: 40px;
        }

        .signature-block {
            text-align: center;
            width: 40%;
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

        /* Right: QR slot */
        .footer-right {
            flex: 0 0 auto;
            padding-right: 10mm;          /* keep off the red frame */
            transform: translateY(-6mm);  /* lift slightly above inner frame */
            text-align: right;
        }

        .qr-code {
            width: 32mm;
            height: 32mm;
            object-fit: contain;
            background: white;
            position: relative;
            left: 30px;
            top: 5px;
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
            background: white;
            position: relative;
            left: 30px;
            top: 5px;
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

                    @if(isset($certificate['certificateImageUrl']))
                        <img src="{{ $certificate['certificateImageUrl'] }}"
                             alt="Certificate Image"
                             class="certificate-competence-image">
                    @endif
                </header>

                <main class="main-content">
                    <p class="certify-text">{{ $certificate['primaryCertifyText'] ?? 'This is to certify that' }}</p>

                    <h2 class="recipient-name">
                        {{ $certificate['recipientName'] ?? 'Recipient Name' }}
                    </h2>

                    <p class="course-title-text">
                        {{ $certificate['certifyText'] }}
                    </p>

                    @if(!empty($certificate['courseTitle']))
                        <h3 class="course-title">
                            {{ $certificate['courseTitle'] }}
                        </h3>
                    @endif

                    <p class="training-details">
                        {{ $certificate['dateLine'] ?? '' }}
                    </p>

                    {{-- List of items for donation/volunteering certificates --}}
                    @if (!empty($certificate['items']) && !empty($certificate['itemHeaders']))
                        <table class="items-table">
                            <thead>
                            <tr>
                                @foreach ($certificate['itemHeaders'] as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($certificate['items'] as $item)
                                @if (isset($item['isSummaryRow']) && $item['isSummaryRow'])
                                    {{-- Special summary row for when items are truncated --}}
                                    <tr>
                                        <td colspan="3" style="text-align: right; font-style: italic;">{{ $item['activity'] ?? $item['description'] }}</td>
                                        <td class="{{ isset($item['hours']) ? 'hours' : 'amount' }}">{{ $item['hours'] ?? $item['amount'] }}</td>
                                    </tr>
                                @else
                                    {{-- Regular data row --}}
                                    <tr>
                                        @foreach ($item as $key => $value)
                                            <td class="{{ $key }}">{{ $value }}</td>
                                        @endforeach
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                            @if (isset($certificate['totalRow']))
                                <tfoot>
                                <tr class="total-row">
                                    <td colspan="3" style="text-align: right;">{{ $certificate['totalRow']['label'] }}</td>
                                    <td class="{{ isset($certificate['totalRow']['hours']) ? 'hours' : 'amount' }}">
                                        {{ $certificate['totalRow']['hours'] ?? $certificate['totalRow']['amount'] }}
                                    </td>
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    @endif
                </main>

                <footer class="footer">
                    <div class="footer-left">
                        <div class="certificate-info">
                            Ref: {!! str_replace('/', '/<wbr>',  ($certificate['user']->user_id_reference ?? '—')) !!} &nbsp;
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
