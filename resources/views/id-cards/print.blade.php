{{--
  Required variables from the controller:
  $cards: an array of card data.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>ID Card Print</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&family=Courier+New&display=swap" rel="stylesheet">

    <!-- Print-focused CSS only -->
    <style type="text/css" media="print">
        @page { size: auto; margin: 2mm; }
        html { background: #fff; margin: 0; }
        body { margin: 0; font-family: 'Open Sans', sans-serif; }
        .no-print { display: none !important; }
        .card-container { page-break-after: always; }
        .card-container:last-child { page-break-after: auto; }
    </style>

    <!-- Minimal screen fallback (optional) -->
    <style>
        body { font-family: 'Open Sans', sans-serif; }
        .printableArea { position: relative; left: 0; top: 0; }
        /* Keep absolute layout from the original */
        .card-container { margin-bottom: 20px; }
    </style>

    <script>
        // Auto-open print dialog when the page loads
        // window.addEventListener('load', function () { window.print(); });
    </script>
</head>
<body>

@foreach($cards as $card)
    <div class="card-container">
        <div class="printableArea">

            <!-- FRONT SIDE -->
            <div class="firstside" style="position: relative; width:960px; height:610px;">
                <img src="{{ $card['img_bg'] }}" style="position:absolute; top:0; left:0; width:960px; height:610px;" alt="">
                <img src="{{ $card['img_header'] }}" style="position:absolute; top:5px; left:210px; width:600px; height:120px;" alt="">
                <img src="{{ $card['img_logo'] }}" style="position:absolute; top:10px; left:10px; width:200px;" alt="">

                <p style="z-index:100; position:absolute; font-size:35px; font-weight:bold; left:295px; top:111px; color:#333;">
                    {!! str_replace('/', '/<wbr>', $card['dbcode']) !!}
                </p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:15px; top:200px;">
                    {{ strtoupper($card['lastname']) }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:16px; top:244px;">Surname</p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:295px; top:200px;">
                    {{ strtoupper($card['firstname']) }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:296px; top:244px;">Firstname</p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:15px; top:300px;">
                    {{ strtoupper($card['national_id_number']) }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:16px; top:344px;">National ID No.</p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:295px; top:300px;">
                    {{ strtoupper($card['membership_type']) }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:296px; top:344px;">Memb. category</p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:15px; top:400px;">
                    {{ strtoupper($card['branch']) }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:16px; top:444px;">Branch</p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:295px; top:400px;">
                    {{ strtoupper($card['division']) }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:296px; top:444px;">Division</p>

                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; left:15px; top:500px;">
                    {{ $card['expdate'] }}
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:16px; top:544px;">Expiry date</p>

                <img src="{{ $card['picture'] }}"   alt="profile photo"
                     style="position:absolute; top:200px; left:660px; width:300px; height: 280px; object-fit: contain;" />
                <img src="{{ $card['signature'] }}" alt="holder signature"
                     style="position:absolute; top:491px; left:298px; width:280px; height:77px; border:1px solid #777; border-radius:3px; object-fit: contain;" />
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; left:296px; top:544px;">Holder's signature</p>
            </div>

            <!-- BACK SIDE -->
            <div class="secondside" style="page-break-before:always; position:relative; width:900px; height:600px; left:0; top:0;">
                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; text-align:center; left:10px; top:10px; right:10px;">
                    This is to certify that the person whose photograph appears on this card is a member of
                </p>
                <p style="z-index:100; position:absolute; font-size:36px; font-weight:900; left:90px; top:90px;">
                    THE NIGERIAN RED CROSS SOCIETY
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; text-align:center; left:10px; top:150px; right:10px;">
                    Plot 589, T.O.S. Benson Street, Off Ngozi Okanjo Iwaela,<br>Utako District, FCT - Abuja
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; text-align:center; left:80px; top:240px;">
                    Impersonation, Alteration or Transfer of this Card is an Offence.
                </p>
                <p style="z-index:100; position:absolute; font-size:24px; font-weight:bold; text-align:center; left:10px; top:270px; right:10px;">
                    If found, please return to the above address or the nearest Red Cross Office.
                </p>

                <p style="z-index:100; position:absolute;  left:560px; top:320px; font-size:20px; font-weight:bold;">
                    Scan to verify authenticity
                </p>

                <img src="{{ $card['qr_image'] }}" alt="QR"
                     style="position:absolute; top:370px; left:580px; width:200px; height:200px;" />
                <img src="{{ $card['img_sg_signature'] }}" alt="Secretary General signature"
                     style="position:absolute; top:380px; left:120px; width:300px; " />
                <p style="z-index:100; position:absolute; font-size:36px; font-weight:bold; text-align:center; left:50px; top:500px;">
                    Secretary General's Signature
                </p>


            </div>
        </div>
    </div>
@endforeach

</body>
</html>
