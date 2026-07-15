<div class="space-y-2">

    {{-- Gender Filter --}}
    <div class="flex flex-col space-y-0.5">
        <label for="gender" class="text-xs font-medium text-gray-700">
            Gender
        </label>
        <select name="gender"
                id="gender"
                class="w-full px-2 py-1 text-xs border border-gray-300 rounded shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 {{ request('gender') ? 'filter-active' : '' }}">
            <option value="">All</option>
            <option value="male"   {{ request('gender') === 'male' ? 'selected' : '' }}>Male</option>
            <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>Female</option>
        </select>
    </div>

    {{-- Age Group --}}
    <div class="flex flex-col space-y-0.5">
        <label for="age_bracket" class="text-xs font-medium text-gray-700">
            Age group
        </label>
        <select name="age_bracket"
                id="age_bracket"
                class="w-full px-2 py-1 text-xs border border-gray-300 rounded shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 {{ request('age_bracket') ? 'filter-active' : '' }}">
            <option value="">All ages</option>
            <optgroup label="Broad groups">
                <option value="1|17"  @selected(request('age_bracket') === '1|17')>Under 18</option>
                <option value="18|35" @selected(request('age_bracket') === '18|35')>Youth (18–35)</option>
                <option value="36|59" @selected(request('age_bracket') === '36|59')>Adults (36–59)</option>
                <option value="60|"   @selected(request('age_bracket') === '60|')>Elderly (60+)</option>
            </optgroup>
            <optgroup label="Detailed groups">
                <option value="1|5"   @selected(request('age_bracket') === '1|5')>Toddlers &amp; pre-school (1–5)</option>
                <option value="6|11"  @selected(request('age_bracket') === '6|11')>Primary school (6–11)</option>
                <option value="12|14" @selected(request('age_bracket') === '12|14')>Junior secondary (12–14)</option>
                <option value="15|17" @selected(request('age_bracket') === '15|17')>Senior secondary (15–17)</option>
                <option value="18|25" @selected(request('age_bracket') === '18|25')>Young adults (18–25)</option>
                <option value="26|35" @selected(request('age_bracket') === '26|35')>Adults (26–35)</option>
                <option value="36|50" @selected(request('age_bracket') === '36|50')>Middle-aged (36–50)</option>
                <option value="51|65" @selected(request('age_bracket') === '51|65')>Senior adults (51–65)</option>
                <option value="66|"   @selected(request('age_bracket') === '66|')>Elderly (66+)</option>
            </optgroup>
        </select>
    </div>

</div>
