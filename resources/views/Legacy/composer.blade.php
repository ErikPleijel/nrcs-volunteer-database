<x-layouts.admin title="Message Composer">
    <x-slot name="pageHeader">
        <i class="fas fa-paper-plane mr-3"></i> Bulk Email/SMS
    </x-slot>
    <x-slot name="subHeader">
        Send Bulk Email and SMS to @if(isset($sourceModule)) {{ Str::title(str_replace('-', ' ', $sourceModule)) }} @else Filtered Users @endif
    </x-slot>



    <div class="container mx-auto px-4 py-6 flex flex-col md:flex-row gap-6"> {{-- Added flex container --}}

        <!-- Recipients List (Left Column) -->
        <div class="w-full md:w-1/3 bg-white shadow rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Recipients ({{ $recipientCount ?? 0 }})</h3>

            {{-- Filter Results Info --}}
            @if($hasFilters)
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4">
                    <div class="text-sm space-y-1">
                        <p class="font-semibold">Applied Filters:</p>
                        @if(isset($displayFilters['search']))
                            <p>Search: <strong>"{{ $displayFilters['search'] }}"</strong></p>
                        @endif
                        @if(isset($displayFilters['my_records']) && $displayFilters['my_records'])
                            <p>Showing <strong>your records only</strong></p>
                        @endif
                        @if(isset($displayFilters['membership_fee_name']))
                            <p>Membership Type: <strong>{{ $displayFilters['membership_fee_name'] }}</strong></p>
                        @endif
                        @if(isset($displayFilters['validity_status']))
                            <p>Status: <strong>{{ $displayFilters['validity_status'] }}</strong></p>
                        @endif
                        @if(isset($displayFilters['branch_name']))
                            <p>Branch: <strong>{{ $displayFilters['branch_name'] }}</strong></p>
                        @endif
                        @if(isset($displayFilters['division_name']))
                            <p>Division: <strong>{{ $displayFilters['division_name'] }}</strong></p>
                        @endif
                        @if(isset($displayFilters['red_cross_unit_name']))
                            <p>Red Cross Unit: <strong>{{ $displayFilters['red_cross_unit_name'] }}</strong></p>
                        @endif
                        <p class="mt-2">({{ $recipientCount }} {{ Str::plural('recipient', $recipientCount) }} found matching filters)</p>
                    </div>
                </div>
            @endif

            {{-- Summary Statistics --}}
            <div class="mb-4 text-sm text-gray-600 space-y-1">
                @if($noEmailCount > 0)
                    <p class="text-red-600"><i class="fas fa-exclamation-triangle mr-1"></i> {{ $noEmailCount }} {{ Str::plural('person', $noEmailCount) }} without Email</p>
                @else
                    <p class="text-green-600"><i class="fas fa-check-circle mr-1"></i> All recipients have Email</p>
                @endif

                @if($noPhoneCount > 0)
                    <p class="text-red-600"><i class="fas fa-exclamation-triangle mr-1"></i> {{ $noPhoneCount }} {{ Str::plural('person', $noPhoneCount) }} without Phone</p>
                @else
                    <p class="text-green-600"><i class="fas fa-check-circle mr-1"></i> All recipients have Phone</p>
                @endif
            </div>
            {{-- End Summary Statistics --}}

            <div class="h-96 overflow-y-auto border border-gray-200 rounded-md"> {{-- Scrollable container --}}
                @if($recipients->isNotEmpty())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100"> {{-- divide-gray-100 for smaller lines --}}
                            @foreach($recipients as $recipient)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5 whitespace-nowrap text-sm"> {{-- py-1.5 for smaller padding --}}
                                        <div class="font-medium text-gray-900">{{ $recipient->full_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $recipient->user_id_reference }}</div>
                                    </td>
                                    <td class="px-3 py-1.5 whitespace-nowrap text-sm text-gray-600"> {{-- py-1.5 for smaller padding --}}
                                        @if($recipient->telephone1)
                                            <div class="flex items-center">
                                                <i class="fas fa-phone-alt text-gray-400 mr-1 text-xs"></i>
                                                {{ $recipient->telephone1 }}
                                            </div>
                                        @else
                                            <div class="flex items-center text-red-500">
                                                <i class="fas fa-times-circle mr-1 text-xs"></i>
                                                No Phone
                                            </div>
                                        @endif
                                        @if($recipient->email)
                                            <div class="flex items-center">
                                                <i class="fas fa-envelope text-gray-400 mr-1 text-xs"></i>
                                                {{ $recipient->email }}
                                            </div>
                                        @else
                                            <div class="flex items-center text-red-500">
                                                <i class="fas fa-times-circle mr-1 text-xs"></i>
                                                No Email
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-4 text-center text-gray-500 text-sm">
                        No recipients found matching the applied filters.
                    </div>
                @endif
            </div>
        </div>

        <!-- Message Composer Form (Right Column) -->
        <div class="w-full md:w-2/3 bg-white shadow rounded-lg p-6">
            <form id="message-composer-form" action="{{ route('messaging.send') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Hidden inputs to preserve filters and source module -->
                @if(isset($sourceModule))
                    <input type="hidden" name="source_module" value="{{ $sourceModule }}">
                @endif
                @if(isset($filters) && is_array($filters))
                    @foreach ($filters as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $item)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                @endif

                <!-- Message Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Type</label>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="message_type" value="email" class="form-radio text-blue-600 h-4 w-4" checked>
                            <span class="ml-2 text-gray-700">Email ({{ $emailAvailableCount }})</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="message_type" value="sms" class="form-radio text-purple-600 h-4 w-4">
                            <span class="ml-2 text-gray-700">SMS ({{ $phoneAvailableCount }})</span>
                        </label>
                    </div>
                </div>

                <!-- Recipient Count Display -->
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                    <p class="text-sm">
                        @if(isset($recipientCount))
                            {{ $recipientCount }} potential recipients based on current filters from the "@if(isset($sourceModule)){{ Str::title(str_replace('-', ' ', $sourceModule)) }}@endif" module.
                            @if ($recipientCount == 0)
                                <span class="font-bold text-red-600">No recipients found with the current filters. Message will not be sent.</span>
                            @endif
                        @else
                            No recipient count available yet. Filters will determine recipients upon sending.
                        @endif
                    </p>
                </div>

                <!-- Email Subject (conditionally visible) -->
                <div id="email-subject-group" class="{{ old('message_type', 'email') === 'sms' ? 'hidden' : '' }}">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                    <input type="text" name="subject" id="subject"
                           value="{{ old('subject') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Enter email subject">
                    @error('subject')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Message Body -->
                <div>
                    <label for="body" class="block text-sm font-medium text-gray-700 mb-2">Message Body</label>

                    {{-- Placeholder Dropdown --}}
                    <div class="mb-2">
                        <select id="placeholder_selector" class="w-full md:w-1/3 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-700">
                            <option value="">Insert Placeholder</option>
                            @foreach($placeholders as $key => $name)
                                <option value="&#64;{{ '{' . '{' . $key . '}' . '}' }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <textarea name="body" id="body" rows="10"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Enter your message">{{ old('body') }}</textarea>
                    <div id="char-count" class="text-right text-sm text-gray-500 mt-1"></div>
                    @error('body')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button type="button" id="previewMessage" class="btn-secondary">
                        <i class="fas fa-eye mr-2"></i> Preview Messages
                    </button>
                    <button type="submit" class="btn-primary" @if(isset($recipientCount) && $recipientCount == 0) disabled @endif>
                        <i class="fas fa-paper-plane mr-2"></i> Send Message
                    </button>
                    <a href="{{ url()->previous() }}" class="btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const messageTypeRadios = document.querySelectorAll('input[name="message_type"]');
            const emailSubjectGroup = document.getElementById('email-subject-group');
            const messageBody = document.getElementById('body');
            const charCountDisplay = document.getElementById('char-count');
            const placeholderSelector = document.getElementById('placeholder_selector');
            const previewMessageButton = document.getElementById('previewMessage'); // Get the preview button
            const messageComposerForm = document.getElementById('message-composer-form'); // Get the form

            function toggleMessageTypeFields() {
                const selectedType = document.querySelector('input[name="message_type"]:checked').value;
                if (selectedType === 'email') {
                    emailSubjectGroup.classList.remove('hidden');
                } else {
                    emailSubjectGroup.classList.add('hidden');
                }
                updateCharCount(); // Update count based on SMS limits if applicable
            }

            function updateCharCount() {
                const currentMessageType = document.querySelector('input[name="message_type"]:checked').value;
                const messageLength = messageBody.value.length;
                if (currentMessageType === 'sms') {
                    const segmentLength = 160; // Standard SMS character limit for a single message (GSM encoding)
                    const segments = Math.ceil(messageLength / segmentLength);
                    charCountDisplay.textContent = `Characters: ${messageLength} (${segments} SMS message${segments > 1 ? 's' : ''})`;
                } else {
                    charCountDisplay.textContent = `Characters: ${messageLength}`;
                }
            }

            // Function to insert text at cursor position
            function insertAtCaret(field, text) {
                const scrollPos = field.scrollTop;
                let caretPos = field.selectionStart;

                const front = (field.value).substring(0, caretPos);
                const back = (field.value).substring(field.selectionEnd, field.value.length);
                field.value = front + text + back;
                caretPos = caretPos + text.length;
                field.selectionStart = caretPos;
                field.selectionEnd = caretPos;
                field.focus();
                field.scrollTop = scrollPos;
            }

            // Event listener for the Preview Message button
            previewMessageButton.addEventListener('click', function () {
                // Temporarily change the form's action and target for the preview
                const originalAction = messageComposerForm.action;
                const originalTarget = messageComposerForm.target;
                const originalMethod = messageComposerForm.method;

                messageComposerForm.action = '{{ route('messaging.preview') }}';
                messageComposerForm.target = '_blank';  // Open in a new tab/window
                messageComposerForm.method = 'POST';

                // Programmatically submit the form
                messageComposerForm.submit();

                // Restore the original form attributes after submission
                messageComposerForm.action = originalAction;
                messageComposerForm.target = originalTarget;
                messageComposerForm.method = originalMethod;
            });

            messageTypeRadios.forEach(radio => {
                radio.addEventListener('change', toggleMessageTypeFields);
            });
            messageBody.addEventListener('input', updateCharCount);

            // Event listener for placeholder selection
            placeholderSelector.addEventListener('change', function() {
                const selectedPlaceholder = this.value;
                if (selectedPlaceholder) {
                    insertAtCaret(messageBody, selectedPlaceholder);
                    this.value = ""; // Reset dropdown to "Insert Placeholder"
                    updateCharCount(); // Update character count after inserting
                }
            });

            // Initial call to set up the fields based on default selection or old input
            toggleMessageTypeFields();
            updateCharCount();
        });
    </script>
</x-layouts.admin>
