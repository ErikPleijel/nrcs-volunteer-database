// resources/js/location-cascade.js

function setupLocationCascade(root) {
    const branchSelect   = root.querySelector('[data-branch-select]');
    const divisionSelect = root.querySelector('[data-division-select]');
    const unitSelect     = root.querySelector('[data-unit-select]');

    if (!branchSelect || !divisionSelect || !unitSelect) {
        return;
    }

    const divisionsUrl = root.dataset.divisionsUrl; // /divisions/by-branch
    const unitsUrl     = root.dataset.unitsUrl;     // /red-cross-units/by-division

    const initialBranch   = root.dataset.selectedBranch || '';
    const initialDivision = root.dataset.selectedDivision || '';
    const initialUnit     = root.dataset.selectedUnit || '';

    // 👇 NEW: figure out the "real" branch, even if the select is disabled
    const resolvedInitialBranch =
        initialBranch ||                      // from data-selected-branch (national)
        (branchSelect.value || '');           // from the actual <select> (branch access)

    function resetAndDisable(selectElement, placeholderText) {
        selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
        selectElement.disabled = true;
        selectElement.classList.add('cursor-not-allowed', 'bg-gray-100');
    }

    async function populateDivisions(branchId, selectedDivisionId = '') {
        if (!branchId) {
            resetAndDisable(divisionSelect, 'Select Branch First');
            resetAndDisable(unitSelect, 'Select Division First');
            return;
        }

        // If division is locked (branch/division access), let backend-defined
        // division + unit options stand. Do NOT touch units here.
        if (divisionSelect.disabled && !divisionSelect.dataset.forceEnable) {
            return;
        }

        // At this point we *are* going to change the divisions,
        // so now it's safe to reset units.
       // resetAndDisable(unitSelect, 'Select Division First');

        divisionSelect.disabled = false;
        divisionSelect.classList.remove('cursor-not-allowed', 'bg-gray-100');
        divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

        try {
            const response = await fetch(`${divisionsUrl}?branch_id=${branchId}`);
            const divisions = await response.json();

            divisionSelect.innerHTML = '<option value="">All Divisions</option>';
            divisions.forEach(division => {
                const option = document.createElement('option');
                option.value = division.id;
                option.textContent = division.name;
                if (String(division.id) === String(selectedDivisionId)) {
                    option.selected = true;
                }
                divisionSelect.appendChild(option);
            });

            // If a specific division is valid, load units
            if (
                selectedDivisionId &&
                Array.from(divisionSelect.options).some(
                    o => String(o.value) === String(selectedDivisionId)
                )
            ) {
                populateUnits(selectedDivisionId, initialUnit);
            } else {
                resetAndDisable(unitSelect, 'Select Division First');
            }
        } catch (e) {
            console.error('Error fetching divisions:', e);
            resetAndDisable(divisionSelect, 'Error loading divisions');
        }
    }


    async function populateUnits(divisionId, selectedUnitId = '') {
        if (!divisionId) {
            resetAndDisable(unitSelect, 'Select Division First');
            return;
        }

        if (unitSelect.disabled && !unitSelect.dataset.forceEnable) {
            return;
        }

        unitSelect.disabled = false;
        unitSelect.classList.remove('cursor-not-allowed', 'bg-gray-100');
        unitSelect.innerHTML = '<option value="">Loading units...</option>';

        try {
            const response = await fetch(`${unitsUrl}?division_id=${divisionId}`);
            const units = await response.json();

            unitSelect.innerHTML = '<option value="">All Units</option>';
            units.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = unit.name;
                if (String(unit.id) === String(selectedUnitId)) {
                    option.selected = true;
                }
                unitSelect.appendChild(option);
            });
        } catch (e) {
            console.error('Error fetching units:', e);
            resetAndDisable(unitSelect, 'Error loading units');
        }
    }

    // Event listeners
    branchSelect.addEventListener('change', () => {
        populateDivisions(branchSelect.value);
    });

    divisionSelect.addEventListener('change', () => {
        populateUnits(divisionSelect.value);
    });

    // 🔑 Initial setup
    // Use the resolvedInitialBranch even if the branch select is disabled (branch access)
    if (resolvedInitialBranch) {
        populateDivisions(resolvedInitialBranch, initialDivision);
    } else if (initialDivision && !divisionSelect.disabled) {
        populateUnits(initialDivision, initialUnit);
    } else if (!resolvedInitialBranch) {
        resetAndDisable(divisionSelect, 'Select Branch First');
        resetAndDisable(unitSelect, 'Select Division First');
    }
}

// Auto-init all instances on page load
document.addEventListener('DOMContentLoaded', () => {
    document
        .querySelectorAll('[data-location-cascade]')
        .forEach(setupLocationCascade);
});
