<x-layouts.admin title="Tutorial Completion">
    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3 mb-6"></i> Tutorial Completion
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('reports.tutorial-completion') }}" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        <div>
                            <label for="name" class="filter-label">Name</label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   value="{{ $name }}"
                                   placeholder="First or last name..."
                                   class="filter-input {{ filled($name) ? 'filter-active' : '' }}">
                        </div>

                        <div>
                            <label for="db_number" class="filter-label">DB-number</label>
                            <input type="text"
                                   id="db_number"
                                   name="db_number"
                                   value="{{ $dbNumber }}"
                                   placeholder="e.g. 1234"
                                   class="filter-input {{ filled($dbNumber) ? 'filter-active' : '' }}">
                        </div>

                        <div>
                            <label for="role" class="filter-label">Role</label>
                            <select name="role" id="role" class="filter-select {{ filled($role) ? 'filter-active' : '' }}">
                                <option value="">All Roles</option>
                                @foreach($roles as $roleOption)
                                    <option value="{{ $roleOption->name }}" {{ $role === $roleOption->name ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $roleOption->name)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if($accessLevel === 'national')
                            <div>
                                <label for="branch_id" class="filter-label">Branch</label>
                                <select name="branch_id" id="branch_id" class="filter-select {{ (filled($branchId) && $branchId !== 'national') ? 'filter-active' : '' }}">
                                    <option value="">National</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (string) $branchId === (string) $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="no_lessons" value="1" {{ $noLessons ? 'checked' : '' }} class="rounded border-gray-300">
                            Show only people with no completed lessons
                        </label>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
                            <a @if($hasFilters) href="{{ route('reports.tutorial-completion') }}" @endif
                               class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results count -->
        <div class="text-gray-600 px-1 pb-2">
            Found {{ $rows->total() }} {{ $rows->total() === 1 ? 'person' : 'people' }}
        </div>

        <!-- Table -->
        <div class="table-container">
            @if($rows->count() > 0)

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                            <tr class="table-header-row">
                                <th class="table-header-cell">Name</th>
                                <th class="table-header-cell">DB-number</th>
                                <th class="table-header-cell">Branch</th>
                                <th class="table-header-cell">Role</th>
                                <th class="table-header-cell">Lessons completed</th>
                                <th class="table-header-cell">Last completed</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            @foreach($rows as $row)
                                <tr class="table-body-row">

                                    <td class="table-body-cell">
                                        <a href="{{ route('users.show', $row->id) }}" class="table-field-main text-indigo-600 hover:underline">
                                            {{ $row->full_name }}
                                        </a>
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $row->id }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $row->branch?->name ?? '—' }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $row->role_display_name }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        @if($row->tutorial_progress_count > 0)
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                    {{ $row->tutorial_progress_count }}
                                                </span>
                                                @foreach($row->tutorialProgress as $progress)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                        {{ ucwords(str_replace(['.', '_'], ' ', $progress->lesson_key)) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-400">
                                                No lessons
                                            </span>
                                        @endif
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">
                                            <x-time-ago :date="$row->last_completed_at" placeholder="—" />
                                        </div>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    {{ $rows->links() }}
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-graduation-cap text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No people found.</h3>
                    <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>

    </div>
</x-layouts.admin>
