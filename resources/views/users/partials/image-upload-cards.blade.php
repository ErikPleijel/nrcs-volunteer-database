{{-- Photo and signature upload cards + camera script. Used by users/images (UserImageController). --}}
                <!-- Profile Photo Management Card -->
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">Profile Photo Management</h2>
                    </div>
                    <div class="p-6">
                        <!-- Current Profile Photo Display -->
                        <div class="flex flex-col items-center mb-6">
                            <div class="w-36 h-54 rounded-xl overflow-hidden flex items-center justify-center border-4 border-white shadow-lg
                                @if(($user->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                                @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                                @if($user->picture)
                                    <img src="{{ $user->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-user text-4xl text-white"></i>
                                @endif
                            </div>
                            @if(!$user->picture)
                                <p class="text-sm text-gray-500 mt-2 text-center">No profile<br>photo uploaded</p>
                            @endif
                            <p class="text-sm text-gray-600 mt-4">Current Profile Photo</p>
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

                        <form method="POST" action="{{ route('users.images.photo', $user) }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-4">
                                <label for="picture" class="block text-sm font-medium text-gray-700 mb-1">Upload New Picture</label>
                                <input type="file" id="picture" name="picture"
                                       class="block w-full text-sm text-gray-500
                                               file:mr-4 file:py-2 file:px-4
                                               file:rounded-md file:border-0
                                               file:text-sm file:font-semibold
                                               file:bg-blue-50 file:text-blue-700
                                               hover:file:bg-blue-100"
                                       accept="image/jpeg,image/png,image/jpg,image/gif">
                                <p class="form-hint" id="file_input_help">JPEG, PNG, JPG, GIF (MAX. 2MB).</p>
                            </div>

                            <div class="relative flex py-5 items-center">
                                <div class="flex-grow border-t border-gray-300"></div>
                                <span class="flex-shrink mx-4 text-gray-400 text-sm">OR</span>
                                <div class="flex-grow border-t border-gray-300"></div>
                            </div>

                            <!-- Camera Section for Profile Photo -->
                            <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                <div id="camera-section-profile" class="text-center">
                                    <video id="camera-video-profile" class="w-full h-48 bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                    <canvas id="camera-canvas-profile" class="hidden"></canvas>
                                    <div id="camera-preview-profile" class="w-full h-48 bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
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

                            <div id="update-photo-button-container" class="flex justify-center mt-6 hidden">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50" id="update-profile-photo-button">
                                    Update Profile Photo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Signature Management Card -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">Signature Management</h2>
                    </div>
                    <div class="p-6">
                        <!-- Current Signature Display -->
                        <div class="flex flex-col items-center mb-6">
                            @if($user->hasSignature())
                                <div class="w-48 h-24 overflow-hidden flex items-center justify-center border-2 border-gray-300 bg-white shadow-sm">
                                    <img src="{{ $user->getSignatureUrlAttribute() }}" alt="User Signature" class="w-full h-full object-contain">
                                </div>
                            @else
                                <div class="w-48 h-24 flex items-center justify-center border-2 border-gray-300 bg-gray-50 text-gray-400 text-xs text-center p-2">
                                    No signature uploaded
                                </div>
                            @endif
                            <p class="text-sm text-gray-600 mt-4">Current Signature</p>
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

                        <form method="POST" action="{{ route('users.images.signature', $user) }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-4">
                                <label for="signature_file" class="block text-sm font-medium text-gray-700 mb-1">Upload New Signature</label>
                                <input type="file" id="signature_file" name="signature_file"
                                       class="block w-full text-sm text-gray-500
                                               file:mr-4 file:py-2 file:px-4
                                               file:rounded-md file:border-0
                                               file:text-sm file:font-semibold
                                               file:bg-blue-50 file:text-blue-700
                                               hover:file:bg-blue-100"
                                       accept="image/jpeg,image/png,image/jpg,image/gif">
                                <p class="form-hint">PNG recommended (MAX. 1MB). Recommended dimensions: 300x150px.</p>
                            </div>

                            <div class="relative flex py-5 items-center">
                                <div class="flex-grow border-t border-gray-300"></div>
                                <span class="flex-shrink mx-4 text-gray-400 text-sm">OR</span>
                                <div class="flex-grow border-t border-gray-300"></div>
                            </div>

                            <!-- Camera Section for Signature -->
                            <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                <div id="camera-section-signature" class="text-center">
                                    <video id="camera-video-signature" class="w-full h-48 bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                    <canvas id="camera-canvas-signature" class="hidden"></canvas>
                                    <div id="camera-preview-signature" class="w-full h-48 bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
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

                            <div id="update-signature-button-container" class="flex justify-center mt-6 hidden">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50" id="update-signature-button">
                                    Update Signature
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
                // Profile Photo Elements
                const startCameraProfileBtn = document.getElementById('start-camera-profile');
                const videoProfile = document.getElementById('camera-video-profile');
                const canvasProfile = document.getElementById('camera-canvas-profile');
                const previewProfile = document.getElementById('camera-preview-profile');
                const capturedImageProfile = document.getElementById('captured-image-profile');
                const capturePhotoProfileBtn = document.getElementById('capture-photo-profile');
                const stopCameraProfileBtn = document.getElementById('stop-camera-profile');
                const usePhotoProfileBtn = document.getElementById('use-photo-profile');
                const retakePhotoProfileBtn = document.getElementById('retake-photo-profile');
                const capturedPhotoDataProfile = document.getElementById('captured-photo-data-profile');
                const captureControlsProfile = document.getElementById('capture-controls-profile');
                const retakeControlsProfile = document.getElementById('retake-controls-profile');
                let streamProfile;

                // Signature Elements
                const startCameraSignatureBtn = document.getElementById('start-camera-signature');
                const videoSignature = document.getElementById('camera-video-signature');
                const canvasSignature = document.getElementById('camera-canvas-signature');
                const previewSignature = document.getElementById('camera-preview-signature');
                const capturedImageSignature = document.getElementById('captured-image-signature');
                const captureSignatureBtn = document.getElementById('capture-signature');
                const stopCameraSignatureBtn = document.getElementById('stop-camera-signature');
                const useSignatureBtn = document.getElementById('use-signature');
                const retakeSignatureBtn = document.getElementById('retake-signature');
                const capturedPhotoDataSignature = document.getElementById('captured-photo-data-signature');
                const captureControlsSignature = document.getElementById('capture-controls-signature');
                const retakeControlsSignature = document.getElementById('retake-controls-signature');
                let streamSignature;

                // Pulsing "Update" button elements
                const pictureFileInput = document.getElementById('picture');
                const updateProfilePhotoButton = document.getElementById('update-profile-photo-button');
                const signatureFileInput = document.getElementById('signature_file');
                const updateSignatureButton = document.getElementById('update-signature-button');

                function updateButtonPulse(fileInput, capturedDataInput, button) {
                    if (!button) return;
                    const hasContent = (fileInput && fileInput.files.length > 0) || (capturedDataInput && capturedDataInput.value !== '');
                    button.classList.toggle('btn-pulsing-shadow', hasContent);
                }

                if (pictureFileInput) {
                    pictureFileInput.addEventListener('change', () => updateButtonPulse(pictureFileInput, capturedPhotoDataProfile, updateProfilePhotoButton));
                }
                if (signatureFileInput) {
                    signatureFileInput.addEventListener('change', () => updateButtonPulse(signatureFileInput, capturedPhotoDataSignature, updateSignatureButton));
                }

                // Shows/hides the submit button for a widget based on whether a file is
                // chosen or a photo/signature has been captured.
                function toggleUpdateButtonVisibility(containerId, fileInputId, capturedDataId) {
                    const container = document.getElementById(containerId);
                    const fileInput = document.getElementById(fileInputId);
                    const capturedData = document.getElementById(capturedDataId);
                    if (!container) return;
                    const hasFile = fileInput?.files?.length > 0;
                    const hasCaptured = capturedData?.value?.length > 0;
                    container.classList.toggle('hidden', !(hasFile || hasCaptured));
                }

                // Wired independently of the camera setup below so the plain
                // file-picker path still works even if the camera never initializes.
                pictureFileInput?.addEventListener('change', () =>
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile'));
                signatureFileInput?.addEventListener('change', () =>
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature'));

                // Initial check (e.g. a validation-error redirect re-rendering the page)
                toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');
                toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');

                // Generic function to stop camera stream
                function stopCamera(stream) {
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                }

                // Generic function to reset camera-related UI
                function resetCameraUI(video, preview, capturedImage, startBtn, captureControls, retakeControls, capturedDataInput, fileInput, button) {
                    video.classList.add('hidden');
                    preview.classList.add('hidden');
                    capturedImage.src = '';
                    startBtn.classList.remove('hidden');
                    captureControls.classList.add('hidden');
                    retakeControls.classList.add('hidden');
                    capturedDataInput.value = '';
                    updateButtonPulse(fileInput, capturedDataInput, button);
                }

                // Generic function to start camera
                async function startCamera(video, type) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                        if (type === 'profile') streamProfile = stream;
                        else streamSignature = stream;

                        video.srcObject = stream;
                        video.classList.remove('hidden');
                        video.classList.add('inline-block'); // Ensure it's visible and occupies space
                        return stream;
                    } catch (err) {
                        console.error("Error accessing camera: ", err);
                        alert("Could not access the camera. Please ensure it's enabled and not in use by another application.");
                        return null;
                    }
                }

                // Profile Photo Logic
                startCameraProfileBtn.addEventListener('click', async () => {
                    stopCamera(streamSignature); // Stop other camera if active
                    resetCameraUI(videoSignature, previewSignature, capturedImageSignature, startCameraSignatureBtn, captureControlsSignature, retakeControlsSignature, capturedPhotoDataSignature, signatureFileInput, updateSignatureButton);
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');

                    startCameraProfileBtn.classList.add('hidden');
                    const stream = await startCamera(videoProfile, 'profile');
                    if (stream) {
                        captureControlsProfile.classList.remove('hidden');
                    } else {
                        startCameraProfileBtn.classList.remove('hidden'); // Show button again if camera failed to start
                    }
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');
                });

                capturePhotoProfileBtn.addEventListener('click', () => {
                    canvasProfile.width = videoProfile.videoWidth;
                    canvasProfile.height = videoProfile.videoHeight;
                    const context = canvasProfile.getContext('2d');
                    context.drawImage(videoProfile, 0, 0, canvasProfile.width, canvasProfile.height);

                    const dataUrl = canvasProfile.toDataURL('image/jpeg', 0.9); // Adjust quality as needed
                    capturedImageProfile.src = dataUrl;
                    capturedPhotoDataProfile.value = dataUrl; // Store Base64 data

                    videoProfile.classList.add('hidden');
                    previewProfile.classList.remove('hidden');
                    captureControlsProfile.classList.add('hidden');
                    retakeControlsProfile.classList.remove('hidden');
                    stopCamera(streamProfile); // Stop the video stream after capturing
                    updateButtonPulse(pictureFileInput, capturedPhotoDataProfile, updateProfilePhotoButton);
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');
                });

                stopCameraProfileBtn.addEventListener('click', () => {
                    stopCamera(streamProfile);
                    resetCameraUI(videoProfile, previewProfile, capturedImageProfile, startCameraProfileBtn, captureControlsProfile, retakeControlsProfile, capturedPhotoDataProfile, pictureFileInput, updateProfilePhotoButton);
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');
                });

                retakePhotoProfileBtn.addEventListener('click', async () => {
                    resetCameraUI(videoProfile, previewProfile, capturedImageProfile, startCameraProfileBtn, captureControlsProfile, retakeControlsProfile, capturedPhotoDataProfile, pictureFileInput, updateProfilePhotoButton);
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');
                    startCameraProfileBtn.classList.add('hidden'); // Keep hidden until camera stream is ready
                    const stream = await startCamera(videoProfile, 'profile');
                    if (stream) {
                        captureControlsProfile.classList.remove('hidden');
                    } else {
                        startCameraProfileBtn.classList.remove('hidden'); // Show button again if camera failed to start
                    }
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');
                });

                usePhotoProfileBtn.addEventListener('click', () => {
                    // Do nothing, the hidden input already holds the data.
                    // The form will submit this data when the main form is submitted.
                    alert('Photo ready for upload. Click Update Profile Picture.');
                });


                // Signature Logic
                startCameraSignatureBtn.addEventListener('click', async () => {
                    stopCamera(streamProfile); // Stop other camera if active
                    resetCameraUI(videoProfile, previewProfile, capturedImageProfile, startCameraProfileBtn, captureControlsProfile, retakeControlsProfile, capturedPhotoDataProfile, pictureFileInput, updateProfilePhotoButton);
                    toggleUpdateButtonVisibility('update-photo-button-container', 'picture', 'captured-photo-data-profile');

                    startCameraSignatureBtn.classList.add('hidden');
                    const stream = await startCamera(videoSignature, 'signature');
                    if (stream) {
                        captureControlsSignature.classList.remove('hidden');
                    } else {
                        startCameraSignatureBtn.classList.remove('hidden'); // Show button again if camera failed to start
                    }
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');
                });

                captureSignatureBtn.addEventListener('click', () => {
                    canvasSignature.width = videoSignature.videoWidth;
                    canvasSignature.height = videoSignature.videoHeight;
                    const context = canvasSignature.getContext('2d');
                    context.drawImage(videoSignature, 0, 0, canvasSignature.width, canvasSignature.height);

                    const dataUrl = canvasSignature.toDataURL('image/png'); // Signatures are typically PNG
                    capturedImageSignature.src = dataUrl;
                    capturedPhotoDataSignature.value = dataUrl; // Store Base64 data

                    videoSignature.classList.add('hidden');
                    previewSignature.classList.remove('hidden');
                    captureControlsSignature.classList.add('hidden');
                    retakeControlsSignature.classList.remove('hidden');
                    stopCamera(streamSignature); // Stop the video stream after capturing
                    updateButtonPulse(signatureFileInput, capturedPhotoDataSignature, updateSignatureButton);
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');
                });

                stopCameraSignatureBtn.addEventListener('click', () => {
                    stopCamera(streamSignature);
                    resetCameraUI(videoSignature, previewSignature, capturedImageSignature, startCameraSignatureBtn, captureControlsSignature, retakeControlsSignature, capturedPhotoDataSignature, signatureFileInput, updateSignatureButton);
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');
                });

                retakeSignatureBtn.addEventListener('click', async () => {
                    resetCameraUI(videoSignature, previewSignature, capturedImageSignature, startCameraSignatureBtn, captureControlsSignature, retakeControlsSignature, capturedPhotoDataSignature, signatureFileInput, updateSignatureButton);
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');
                    startCameraSignatureBtn.classList.add('hidden');
                    const stream = await startCamera(videoSignature, 'signature');
                    if (stream) {
                        captureControlsSignature.classList.remove('hidden');
                    } else {
                        startCameraSignatureBtn.classList.remove('hidden');
                    }
                    toggleUpdateButtonVisibility('update-signature-button-container', 'signature_file', 'captured-photo-data-signature');
                });

                useSignatureBtn.addEventListener('click', () => {
                    // Do nothing, the hidden input already holds the data.
                    alert('Signature ready for upload when you update the signature.');
                });
        });
    </script>
@endpush
