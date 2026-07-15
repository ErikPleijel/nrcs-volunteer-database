<x-layouts.app :title="'Update Signature: ' . (auth()->user()->full_name ?? 'Unknown User')">

    @push('styles')
    <style>
        @keyframes pulse-shadow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.5); }
            50%       { box-shadow: 0 0 0 18px rgba(99, 102, 241, 0); }
        }
        .btn-pulsing-shadow {
            animation: pulse-shadow 1.2s ease-in-out infinite;
        }
    </style>
    @endpush

    <div class="container mx-auto px-4 py-6">
        <div class="max-w-2xl mx-auto">
            <div class="text-center mb-5">
                <a href="{{ route('profile.show') }}"
                   class="btn-backlink">
                    ← Back to Profile
                </a>
            </div>
            <!-- Signature Management Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden">

                <div class="p-6">
                    <!-- Current Signature Display -->
                    <div class="flex flex-col items-center mb-6">
                        @if(auth()->user()->hasSignature())
                            <div class="w-48 h-24 overflow-hidden flex items-center justify-center border-2 border-gray-300 bg-white shadow-sm">
                                <img src="{{ auth()->user()->getSignatureUrlAttribute() }}" alt="User Signature" class="w-full h-full object-contain">
                            </div>
                        @else
                            <div class="w-48 h-24 flex items-center justify-center border-2 border-gray-300 bg-gray-50 text-gray-400 text-xs text-center p-2">
                                No signature uploaded
                            </div>
                        @endif
                        <p class="text-sm text-gray-600 mt-4">Current Signature</p>
                        @if(auth()->user()->hasSignature())
                            <a href="{{ route('profile.show') }}"
                               class="mt-3 inline-flex items-center gap-1 text-sm text-green-700 font-medium border border-green-400 bg-green-50 hover:bg-green-100 px-4 py-1.5 rounded-full transition">
                                <i class="fas fa-check-circle text-green-500"></i> Signature looks good — back to profile
                            </a>
                        @endif
                    </div>

                    @if ($errors->has('signature_file') || $errors->has('captured_signature'))
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-600 text-sm">
                                @foreach ($errors->all() as $error)
                                    {{ $error }}<br>
                                @endforeach
                            </p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update-signature') }}" enctype="multipart/form-data" id="signature-form">
                        @csrf

                        <div class="mb-4 text-center">
                            <label for="signature_file" class="block text-sm font-medium text-gray-700 mb-1">Upload New Signature</label>
                            <input type="file" id="signature_file" name="signature_file"
                                   class="inline-block text-sm text-gray-500
                                           file:mr-4 file:py-2 file:px-4
                                           file:rounded-md file:border-0
                                           file:text-sm file:font-semibold
                                           file:bg-blue-50 file:text-blue-700
                                           hover:file:bg-blue-100"
                                   accept="image/jpeg,image/png,image/jpg,image/gif">
                            <p class="mt-1 text-sm text-gray-500">PNG recommended (MAX. 1MB). Recommended dimensions: 300x150px.</p>
                        </div>

                        {{-- Moved "Update Signature" button here, initially hidden --}}
                        <div id="update-signature-button-container" class="flex justify-center mt-6 hidden">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50" id="update-signature-button">
                                Update Signature
                            </button>
                        </div>

                        <div class="relative flex py-5 items-center">
                            <div class="flex-grow border-t border-gray-300"></div>
                            <span class="flex-shrink mx-4 text-gray-400 text-sm">OR</span>
                            <div class="flex-grow border-t border-gray-300"></div>
                        </div>

                        <!-- Camera Section for Signature -->
                        <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                            <div id="camera-section-signature" class="text-center">
                                <video id="camera-video-signature" class="w-full aspect-[2/1] bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                <canvas id="camera-canvas-signature" class="hidden"></canvas>
                                <div id="camera-preview-signature" class="w-full aspect-[2/1] bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
                                    <img id="captured-image-signature" class="max-w-full max-h-full object-contain rounded-lg" alt="Captured Signature" />
                                </div>

                                <div id="camera-controls-signature">
                                    <button type="button" id="start-camera-signature" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 mb-2">
                                        ✍️ Capture Signature
                                    </button>
                                    <div id="capture-controls-signature" class="hidden space-x-2">
                                        <button type="button" id="capture-signature" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                            📸 Capture
                                        </button>
                                        <button type="button" id="stop-camera-signature" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                                            ❌ Cancel
                                        </button>
                                    </div>
                                    <div id="retake-controls-signature" class="hidden space-x-2">
                                        <button type="button" id="use-signature" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                            ✅ Use Signature
                                        </button>
                                        <button type="button" id="retake-signature" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200">
                                            🔄 Retake
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="captured_signature" id="captured-photo-data-signature">
                                <p class="text-xs text-gray-600 mt-2">Use your camera to capture a signature (e.g., written on paper).</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function setupCameraFeature(config) {
                const startCameraButton = document.getElementById(config.startCameraButtonId);
                const videoElement = document.getElementById(config.videoElementId);
                const canvasElement = document.getElementById(config.canvasElementId);
                const previewElement = document.getElementById(config.previewElementId);
                const capturedImage = document.getElementById(config.capturedImageId);
                const capturedInput = document.getElementById(config.capturedInputId);
                const captureButton = document.getElementById(config.captureButtonId);
                const stopButton = document.getElementById(config.stopButtonId);
                const useButton = document.getElementById(config.useButtonId);
                const retakeButton = document.getElementById(config.retakeButtonId);
                const captureControls = document.getElementById(config.captureControlsId);
                const retakeControls = document.getElementById(config.retakeControlsId);
                const section = document.getElementById(config.sectionId);
                const form = capturedInput ? capturedInput.closest('form') : null;

                // Elements for update button visibility
                const uploadFileInput = document.getElementById('signature_file'); // Correct ID for signature file input
                const updateButtonContainer = document.getElementById('update-signature-button-container'); // Correct ID for signature update button container

                let stream;

                function toggleUpdateButtonVisibility() {
                    if (uploadFileInput && capturedInput && updateButtonContainer) {
                        const hasContent = uploadFileInput.files.length > 0 || capturedInput.value !== '';
                        if (hasContent) {
                            updateButtonContainer.classList.remove('hidden');
                            const btn = document.getElementById('update-signature-button');
                            if (btn) btn.classList.add('btn-pulsing-shadow');
                        } else {
                            updateButtonContainer.classList.add('hidden');
                            const btn = document.getElementById('update-signature-button');
                            if (btn) btn.classList.remove('btn-pulsing-shadow');
                        }
                    }
                }

                function resetCameraUI() {
                    if (videoElement) videoElement.classList.add('hidden');
                    if (previewElement) previewElement.classList.add('hidden');
                    if (captureControls) captureControls.classList.add('hidden');
                    if (retakeControls) retakeControls.classList.add('hidden');
                    if (startCameraButton) startCameraButton.classList.remove('hidden');
                    if (capturedInput) capturedInput.value = '';
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                        stream = null;
                    }
                    toggleUpdateButtonVisibility(); // Update button visibility after resetting camera
                }

                if (section) {
                    resetCameraUI();
                }

                if (startCameraButton) {
                    startCameraButton.addEventListener('click', async () => {
                        try {
                            stream = await navigator.mediaDevices.getUserMedia({
                                video: {
                                    facingMode: 'user'
                                }
                            });
                            if (videoElement) videoElement.srcObject = stream;
                            if (videoElement) videoElement.classList.remove('hidden');
                            if (startCameraButton) startCameraButton.classList.add('hidden');
                            if (captureControls) captureControls.classList.remove('hidden');
                            if (previewElement) previewElement.classList.add('hidden');
                            toggleUpdateButtonVisibility(); // Re-evaluate button visibility when camera starts
                        } catch (err) {
                            console.error("Error accessing camera: ", err);
                            alert("Could not access camera. Please check permissions.");
                            resetCameraUI();
                        }
                    });
                }

                if (captureButton) {
                    captureButton.addEventListener('click', () => {
                        if (!videoElement || !canvasElement || !capturedImage || !capturedInput || !previewElement || !captureControls || !retakeControls) {
                            console.error("Missing camera elements for capture.");
                            return;
                        }

                        canvasElement.width = videoElement.videoWidth;
                        canvasElement.height = videoElement.videoHeight;

                        if (config.aspectRatio) {
                            const videoAspectRatio = videoElement.videoWidth / videoElement.videoHeight;
                            let drawWidth = videoElement.videoWidth;
                            let drawHeight = videoElement.videoHeight;
                            let offsetX = 0;
                            let offsetY = 0;

                            if (videoAspectRatio > config.aspectRatio) {
                                drawWidth = videoElement.videoHeight * config.aspectRatio;
                                offsetX = (videoElement.videoWidth - drawWidth) / 2;
                            } else if (videoAspectRatio < config.aspectRatio) {
                                drawHeight = videoElement.videoWidth / config.aspectRatio;
                                offsetY = (videoElement.videoHeight - drawHeight) / 2;
                            }

                            canvasElement.width = drawWidth;
                            canvasElement.height = drawHeight;
                            canvasElement.getContext('2d').drawImage(videoElement, offsetX, offsetY, drawWidth, drawHeight, 0, 0, drawWidth, drawHeight);
                        } else {
                            canvasElement.getContext('2d').drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
                        }

                        const imageDataUrl = canvasElement.toDataURL('image/png');
                        capturedImage.src = imageDataUrl;
                        capturedInput.value = imageDataUrl;

                        videoElement.classList.add('hidden');
                        previewElement.classList.remove('hidden');
                        captureControls.classList.add('hidden');
                        retakeControls.classList.remove('hidden');

                        if (stream) {
                            stream.getTracks().forEach(track => track.stop());
                            stream = null;
                        }
                        toggleUpdateButtonVisibility(); // Show button after capturing
                    });
                }

                if (stopButton) {
                    stopButton.addEventListener('click', () => {
                        resetCameraUI();
                    });
                }

                if (useButton) { // Add event listener for the "Use Signature" button
                    useButton.addEventListener('click', () => {
                        if (form) {
                            form.submit(); // Submit the form when "Use Signature" is clicked
                        }
                    });
                }

                if (retakeButton) {
                    retakeButton.addEventListener('click', () => {
                        resetCameraUI();
                        if (startCameraButton) startCameraButton.click();
                    });
                }

                // Event listener for the file input to show/hide the update button
                if (uploadFileInput) {
                    uploadFileInput.addEventListener('change', toggleUpdateButtonVisibility);
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const signatureSection = document.getElementById('camera-section-signature');
                if (signatureSection) {
                    setupCameraFeature({
                        startCameraButtonId: 'start-camera-signature',
                        videoElementId: 'camera-video-signature',
                        canvasElementId: 'camera-canvas-signature',
                        previewElementId: 'camera-preview-signature',
                        capturedImageId: 'captured-image-signature',
                        capturedInputId: 'captured-photo-data-signature',
                        captureButtonId: 'capture-signature',
                        stopButtonId: 'stop-camera-signature',
                        useButtonId: 'use-signature',
                        retakeButtonId: 'retake-signature',
                        captureControlsId: 'capture-controls-signature',
                        retakeControlsId: 'retake-controls-signature',
                        sectionId: 'camera-section-signature',
                        aspectRatio: 2 // Signature aspect ratio
                    });
                }

                // Initial check for button visibility on page load
                const uploadFileInput = document.getElementById('signature_file'); // Correct ID
                const capturedSignatureInput = document.getElementById('captured-photo-data-signature'); // Correct ID
                const updateButtonContainer = document.getElementById('update-signature-button-container'); // Correct ID

                if (uploadFileInput && capturedSignatureInput && updateButtonContainer) {
                    if (uploadFileInput.files.length > 0 || capturedSignatureInput.value !== '') {
                        updateButtonContainer.classList.remove('hidden');
                    } else {
                        updateButtonContainer.classList.add('hidden');
                    }
                }
            });
        </script>
    @endpush
</x-layouts.app>
