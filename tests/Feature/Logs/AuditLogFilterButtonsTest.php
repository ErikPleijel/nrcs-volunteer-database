<?php

/**
 * Audit Log filters use the shared Filter / Clear buttons. "Reset" was read
 * as "Delete", so the link that removes the filters says "Clear" and is
 * greyed out (no link) when no filter is set.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_log'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_log']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');
});

test('the audit log has Filter and Clear buttons, not Reset', function () {
    $html = $this->actingAs($this->admin)->get(route('logs.index'))->assertOk()->getContent();

    expect($html)->toContain('filter-btn-primary')
        ->toMatch('/class="filter-btn-secondary filter-btn-disabled">\s*<i class="fas fa-times mr-1"><\/i>Clear/')
        ->not->toMatch('/>\s*Reset\s*</')
        ->not->toContain('Apply filters');
});

test('Clear links back to the unfiltered log when a filter is set', function () {
    $html = $this->actingAs($this->admin)->get(route('logs.index', ['search' => 'x']))->assertOk()->getContent();

    expect($html)->toMatch('/href="'.preg_quote(route('logs.index'), '/').'"\s*class="filter-btn-secondary filter-btn-secondary-active"/');
});
