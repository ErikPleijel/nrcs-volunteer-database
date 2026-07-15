<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Message Preview</title>
    <!-- Include your main CSS if you want the preview to look consistent with your app -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            background-color: #f7f7f7;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 1.5rem;
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .message-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            background-color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #f0f0f0;
            padding: 1rem;
            border-radius: 0.25rem;
            border: 1px solid #ddd;
            max-height: 200px; /* Limit height for body preview */
            overflow-y: auto; /* Add scroll if content is too long */
        }
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #f87171;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="text-2xl font-bold mb-4">Bulk Message Preview ({{ ucfirst($messageType) }}s)</h1>

    @if(isset($error))
        <div class="error-message" role="alert">
            <p class="font-bold">Error:</p>
            <p>{{ $error }}</p>
        </div>
    @elseif(empty($messages)) {{-- Changed from $messages->isEmpty() to empty($messages) --}}
        <div class="error-message" role="alert">
            <p class="font-bold">No Messages to Preview:</p>
            <p>No recipients were found matching the applied filters, or no valid contact information (email/phone) was available for the selected message type.</p>
        </div>
    @else
        <p class="mb-6 text-gray-700">Displaying {{ count($messages) }} personalized messages out of {{ $totalRecipients ?? 'all' }} potential recipients.</p>

        @foreach($messages as $message)
            <div class="message-card">
                <h2 class="text-xl font-semibold mb-2 text-indigo-700">Recipient: {{ $message['recipientName'] }}</h2>
                <div class="mb-3 text-sm text-gray-600">
                    @if($message['messageType'] === 'email' && $message['email'])
                        <p><i class="fas fa-envelope mr-1"></i> To: <strong>{{ $message['email'] }}</strong></p>
                    @elseif($message['messageType'] === 'email')
                        <p class="text-red-500"><i class="fas fa-exclamation-triangle mr-1"></i> No email address for this recipient.</p>
                    @endif

                    @if($message['messageType'] === 'sms' && $message['phone'])
                        <p><i class="fas fa-phone-alt mr-1"></i> To: <strong>{{ $message['phone'] }}</strong></p>
                    @elseif($message['messageType'] === 'sms')
                        <p class="text-red-500"><i class="fas fa-exclamation-triangle mr-1"></i> No phone number for this recipient.</p>
                    @endif
                </div>

                @if($message['messageType'] === 'email')
                    <h3 class="text-lg font-medium mb-1">Subject:</h3>
                    <p class="mb-3 p-2 bg-gray-50 border border-gray-200 rounded-md">{{ $message['subject'] }}</p>
                @endif

                <h3 class="text-lg font-medium mb-1">Body:</h3>
                <pre>{{ $message['body'] }}</pre>

                @if($message['messageType'] === 'sms')
                    @php
                        $messageLength = strlen($message['body']);
                        $segmentLength = 160; // Standard SMS character limit
                        $segments = ceil($messageLength / $segmentLength);
                    @endphp
                    <div class="mt-3 text-sm text-gray-600">
                        <p>SMS Length: {{ $messageLength }} characters</p>
                        <p>Estimated SMS segments: {{ $segments }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    <div class="mt-6 text-center">
        <button onclick="window.close()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
            Close Preview
        </button>
    </div>
</div>
</body>
</html>
