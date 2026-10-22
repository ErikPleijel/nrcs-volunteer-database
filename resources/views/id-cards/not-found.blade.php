<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NRCS Member Verification</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f7fafc;
        }
    </style>
</head>
<body class="font-sans antialiased">
<div class="container mx-auto p-4 md:p-8 max-w-lg">
    <div class="bg-white rounded-lg shadow-xl overflow-hidden">
        <div class="p-6 bg-red-700 text-white text-center">
            <div class="flex justify-center mb-4">
                <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-white bg-white">
                    <img src="{{ asset('images/id-card/NRCS_logo.jpg') }}"
                         alt="NRCS Logo"
                         class="object-cover w-full h-full pr-[2px] pb-[2px]">
                </div>
            </div>
            <h1 class="text-2xl font-bold">Nigerian Red Cross Society</h1>
            <p class="text-md">ID-Card Verification</p>
        </div>

        <div class="p-8 text-center">
            <h2 class="text-2xl font-extrabold text-gray-800 flex items-center justify-center gap-3">
                <span class="inline-flex bg-red-500 rounded-full p-1.5">
                    <i class="fas fa-times text-white text-sm"></i>
                </span>
                This ID card could not be verified
            </h2>
            <p class="mt-6 text-gray-600">
                The QR code does not match any card in our records. The card may have been
                replaced, or the code may not have been read correctly — please scan it again.
            </p>
            <p class="mt-4 text-gray-600">
                If it still cannot be verified, please contact the Nigerian Red Cross Society branch.
            </p>
        </div>
    </div>
</div>
</body>
</html>
