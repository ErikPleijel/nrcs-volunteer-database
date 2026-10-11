{{--
    Branch + division for the organisation create and edit forms.
    $branches            branches to choose from (one = locked)
    $divisionsByBranch   branch id => [{id, name}], from OrganisationController::divisionsByBranch()
    $selectedBranch      current branch id
    $selectedDivision    current division id
    $moveNote            show the "only future payments" note (edit form)
--}}
@php
    $lockBranch = $branches->count() === 1;
    $selectedBranch = old('branch_id', $selectedBranch ?? ($lockBranch ? $branches->first()->id : null));
    $selectedDivision = old('division_id', $selectedDivision ?? null);
@endphp

<div class="form-section">
    <label for="branch_id" class="form-label">
        Branch <span class="text-red-500">*</span>
    </label>
    @if($lockBranch)
        <input type="text" value="{{ $branches->first()->name }}" class="form-input bg-gray-100 text-gray-600" disabled>
        <input type="hidden" name="branch_id" id="branch_id" value="{{ $branches->first()->id }}">
    @else
        <select name="branch_id" id="branch_id" required
                class="form-select @error('branch_id') form-select-error @enderror">
            <option value="">Select Branch</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    @endif
    @error('branch_id')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-section">
    <label for="division_id" class="form-label">
        Division <span class="text-red-500">*</span>
    </label>
    <select name="division_id" id="division_id" required
            class="form-select @error('division_id') form-select-error @enderror">
        <option value="">Select Division</option>
        @foreach($divisionsByBranch[$selectedBranch] ?? [] as $division)
            <option value="{{ $division['id'] }}" @selected((string) $selectedDivision === (string) $division['id'])>{{ $division['name'] }}</option>
        @endforeach
    </select>
    @error('division_id')
        <p class="form-error">{{ $message }}</p>
    @enderror
    @if($moveNote ?? false)
        <p class="text-sm text-gray-500 mt-1">
            Changing the branch or division only affects payments and donations recorded from now on.
            Earlier ones keep the branch and division they were recorded with.
        </p>
    @endif
</div>

@unless($lockBranch)
    <script>
        (function () {
            const divisionsByBranch = @json($divisionsByBranch);
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');

            branchSelect.addEventListener('change', function () {
                divisionSelect.innerHTML = '<option value="">Select Division</option>';
                (divisionsByBranch[this.value] || []).forEach(function (division) {
                    const option = document.createElement('option');
                    option.value = division.id;
                    option.textContent = division.name;
                    divisionSelect.appendChild(option);
                });
            });
        })();
    </script>
@endunless
