{{-- Shared by courses.create and courses.edit. Expects $course, $accessLevel, $branches, $divisions, $trainingTypes. --}}
@php
    $inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500';
    $selectedBranchId = old('branch_id', $accessLevel === 'national' ? $course->branch_id : $branches->first()?->id);
    $selectedDivisionId = old('division_id', $course->division_id);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="md:col-span-2">
        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
            Course name <span class="text-red-500">*</span>
        </label>
        <input type="text" name="name" id="name" maxlength="150" required
               value="{{ old('name', $course->name) }}"
               placeholder="e.g. First Aid for volunteers, Ikeja, March 2027"
               class="{{ $inputClass }} @error('name') border-red-500 @enderror">
        @error('name')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="training_type_id" class="block text-sm font-medium text-gray-700 mb-2">
            Training type <span class="text-red-500">*</span>
        </label>
        <select name="training_type_id" id="training_type_id" required
                class="{{ $inputClass }} @error('training_type_id') border-red-500 @enderror">
            <option value="">Select training type</option>
            @foreach($trainingTypes as $type)
                <option value="{{ $type->id }}" @selected((string) old('training_type_id', $course->training_type_id) === (string) $type->id)>
                    {{ $type->name }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Participants who pass get a training record of this type.</p>
        @error('training_type_id')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
            Start date <span class="text-red-500">*</span>
        </label>
        <input type="date" name="start_date" id="start_date" required
               value="{{ old('start_date', $course->start_date?->toDateString()) }}"
               class="{{ $inputClass }} @error('start_date') border-red-500 @enderror">
        @error('start_date')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
            End date <span class="text-red-500">*</span>
        </label>
        <input type="date" name="end_date" id="end_date" required
               value="{{ old('end_date', $course->end_date?->toDateString()) }}"
               class="{{ $inputClass }} @error('end_date') border-red-500 @enderror">
        <p class="text-xs text-gray-500 mt-1">Same as the start date for a one-day course.</p>
        @error('end_date')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="place" class="block text-sm font-medium text-gray-700 mb-2">Place</label>
        <input type="text" name="place" id="place" maxlength="191"
               value="{{ old('place', $course->place) }}"
               placeholder="e.g. Branch office, Ikeja"
               class="{{ $inputClass }} @error('place') border-red-500 @enderror">
        @error('place')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">Organising branch</label>
        @if($accessLevel === 'national')
            <select name="branch_id" id="branch_id"
                    class="{{ $inputClass }} @error('branch_id') border-red-500 @enderror">
                <option value="">National course (no branch)</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        @else
            <input type="text" value="{{ $branches->first()?->name }}" disabled
                   class="{{ $inputClass }} bg-gray-100 cursor-not-allowed">
            <p class="text-xs text-gray-500 mt-1">You organise courses for your own branch.</p>
        @endif
        @error('branch_id')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="division_id" class="block text-sm font-medium text-gray-700 mb-2">Organising division (optional)</label>
        <select name="division_id" id="division_id"
                data-divisions-url="{{ url('/divisions/by-branch') }}"
                @disabled(! $selectedBranchId)
                class="{{ $inputClass }} {{ $selectedBranchId ? '' : 'bg-gray-100 cursor-not-allowed' }} @error('division_id') border-red-500 @enderror">
            <option value="">{{ $selectedBranchId ? 'Whole branch' : 'Select a branch first' }}</option>
            @foreach($divisions as $division)
                <option value="{{ $division->id }}" @selected((string) $selectedDivisionId === (string) $division->id)>{{ $division->name }}</option>
            @endforeach
        </select>
        @error('division_id')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="external_facilitators" class="block text-sm font-medium text-gray-700 mb-2">External facilitators (optional)</label>
        <textarea name="external_facilitators" id="external_facilitators" rows="2" maxlength="2000"
                  placeholder="Facilitators who are not in the database, e.g. IFRC trainer, State Ministry of Health"
                  class="{{ $inputClass }} @error('external_facilitators') border-red-500 @enderror">{{ old('external_facilitators', $course->external_facilitators) }}</textarea>
        <p class="text-xs text-gray-500 mt-1">Facilitators who are in the database are added on the course page, like participants.</p>
        @error('external_facilitators')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

@if($accessLevel === 'national')
    <script>
        // Reload the division list when a national admin changes the organising branch.
        document.addEventListener('DOMContentLoaded', () => {
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');

            branchSelect.addEventListener('change', async () => {
                const branchId = branchSelect.value;
                divisionSelect.innerHTML = '';

                if (!branchId) {
                    divisionSelect.add(new Option('Select a branch first', ''));
                    divisionSelect.disabled = true;
                    divisionSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
                    return;
                }

                divisionSelect.add(new Option('Loading divisions...', ''));
                const response = await fetch(`${divisionSelect.dataset.divisionsUrl}?branch_id=${encodeURIComponent(branchId)}`);
                const divisions = await response.json();

                divisionSelect.innerHTML = '';
                divisionSelect.add(new Option('Whole branch', ''));
                divisions.forEach(division => divisionSelect.add(new Option(division.name, division.id)));
                divisionSelect.disabled = false;
                divisionSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
            });
        });
    </script>
@endif
