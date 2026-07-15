<x-layouts.app :title="'Update Profile Photo: ' . (auth()->user()->full_name ?? 'Unknown User')">

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
            <!-- Profile Photo Management Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden mb-6">

                <div class="p-6">
                    <!-- Current Profile Photo Display -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="w-32 h-44 overflow-hidden flex items-center justify-center border-4 border-white shadow-lg rounded-lg
                            @if((auth()->user()->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                            @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                            @if(auth()->user()->picture)
                                <img src="{{ auth()->user()->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                            @else
                                <i class="fas fa-user text-4xl text-white"></i>
                            @endif
                        </div>
                        @if(!auth()->user()->picture)
                            <p class="text-sm text-gray-500 mt-2 text-center">No profile<br>photo uploaded</p>
                        @endif
                        <p class="text-sm text-gray-600 mt-4">Current Profile Photo</p>
                        @if(auth()->user()->picture)
                            <a href="{{ route('profile.show') }}"
                               class="mt-3 inline-flex items-center gap-1 text-sm text-green-700 font-medium border border-green-400 bg-green-50 hover:bg-green-100 px-4 py-1.5 rounded-full transition">
                                <i class="fas fa-check-circle text-green-500"></i> My photo looks fine — back to profile
                            </a>
                        @endif
                    </div>


                    @if ($errors->has('picture') || $errors->has('captured_photo'))
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-600 text-sm">
                                @foreach ($errors->all() as $error)
                                    {{ $error }}<br>
                                @endforeach
                            </p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update-profile-picture') }}" enctype="multipart/form-data" id="profile-photo-form">
                        @csrf

                        <div class="mb-4 text-center">
                            <label for="picture" class="block text-sm font-medium text-gray-700 mb-1">Upload New Picture</label>
                            <input type="file" id="picture" name="picture"
                                   class="inline-block text-sm text-gray-500
                                           file:mr-4 file:py-2 file:px-4
                                           file:rounded-md file:border-0
                                           file:text-sm file:font-semibold
                                           file:bg-blue-50 file:text-blue-700
                                           hover:file:bg-blue-100"
                                   accept="image/jpeg,image/png,image/jpg,image/gif">
                            <p class="mt-1 text-sm text-gray-500" id="file_input_help">JPEG, PNG, JPG, GIF (MAX. 2MB).</p>
                        </div>

                        {{-- Moved "Update Profile Photo" button here, initially hidden --}}
                        <div id="update-photo-button-container" class="flex justify-center mt-6 hidden">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50" id="update-profile-photo-button">
                                Update Profile Photo
                            </button>
                        </div>

                        <div class="relative flex py-5 items-center">
                            <div class="flex-grow border-t border-gray-300"></div>
                            <span class="flex-shrink mx-4 text-gray-400 text-sm">OR</span>
                            <div class="flex-grow border-t border-gray-300"></div>
                        </div>

                        <!-- Camera Section for Profile Photo -->
                        <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                            <div id="camera-section-profile" class="text-center">
                                <video id="camera-video-profile" class="w-full aspect-video bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                <canvas id="camera-canvas-profile" class="hidden"></canvas>
                                <div id="camera-preview-profile" class="w-full aspect-video bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
                                    <img id="captured-image-profile" class="max-w-full max-h-full object-contain rounded-lg" alt="Captured Photo" />
                                </div>

                                <div id="camera-controls-profile">
                                    <button type="button" id="start-camera-profile" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 mb-2">
                                        📷 Take Photo
                                    </button>
                                    <div id="capture-controls-profile" class="hidden space-x-2">
                                        <button type="button" id="capture-photo-profile" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                            📸 Capture
                                        </button>
                                        <button type="button" id="stop-camera-profile" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                                            ❌ Cancel
                                        </button>
                                    </div>
                                    <div id="retake-controls-profile" class="hidden space-x-2">
                                        <button type="button" id="use-photo-profile" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                            ✅ Use Photo
                                        </button>
                                        <button type="button" id="retake-photo-profile" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200">
                                            🔄 Retake
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="captured_photo" id="captured-photo-data-profile">
                                <p class="text-xs text-gray-600 mt-2">Click "Take Photo" to use your camera.</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function toggleUpdateButtonVisibility() {
                const uploadFileInput = document.getElementById('picture');
                const capturedInput = document.getElementById('captured-photo-data-profile');
                const updateButtonContainer = document.getElementById('update-photo-button-container');
                const btn = document.getElementById('update-profile-photo-button');

                if (uploadFileInput && capturedInput && updateButtonContainer) {
                    const hasContent = uploadFileInput.files.length > 0 || capturedInput.value !== '';
                    if (hasContent) {
                        updateButtonContainer.classList.remove('hidden');
                        if (btn) btn.classList.add('btn-pulsing-shadow');
                    } else {
                        updateButtonContainer.classList.add('hidden');
                        if (btn) btn.classList.remove('btn-pulsing-shadow');
                    }
                }
            }

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

                let stream;

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

                if (useButton) {
                    useButton.addEventListener('click', () => {
                        if (form) {
                            form.submit();
                        }
                    });
                }

                if (retakeButton) {
                    retakeButton.addEventListener('click', () => {
                        resetCameraUI();
                        if (startCameraButton) startCameraButton.click();
                    });
                }

            }

            document.addEventListener('DOMContentLoaded', function () {
                // Wire file-input listener independently — not dependent on camera setup succeeding
                const pictureInput = document.getElementById('picture');
                if (pictureInput) {
                    pictureInput.addEventListener('change', toggleUpdateButtonVisibility);
                }

                // Initial visibility check
                toggleUpdateButtonVisibility();

                const profilePhotoSection = document.getElementById('camera-section-profile');
                if (profilePhotoSection) {
                    setupCameraFeature({
                        startCameraButtonId: 'start-camera-profile',
                        videoElementId: 'camera-video-profile',
                        canvasElementId: 'camera-canvas-profile',
                        previewElementId: 'camera-preview-profile',
                        capturedImageId: 'captured-image-profile',
                        capturedInputId: 'captured-photo-data-profile',
                        captureButtonId: 'capture-photo-profile',
                        stopButtonId: 'stop-camera-profile',
                        useButtonId: 'use-photo-profile',
                        retakeButtonId: 'retake-photo-profile',
                        captureControlsId: 'capture-controls-profile',
                        retakeControlsId: 'retake-controls-profile',
                        sectionId: 'camera-section-profile',
                        aspectRatio: 1
                    });
                }
            });
        </script>
    @endpush
</x-layouts.app>
