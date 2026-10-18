<?php

/**
 * Tutorial Completion report: the DB-number is the (underlined) link to
 * users/show; the name is plain text.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the DB-number links to the person and the name is plain text', function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_reports'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_reports']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $admin->assignRole('national_db_administrator');

    $html = $this->actingAs($admin)->get(route('reports.tutorial-completion'))->assertOk()->getContent();

    $url = preg_quote(route('users.show', $admin->id), '/');

    expect($html)->toMatch('/<a href="'.$url.'" class="[^"]*\bunderline\b[^"]*" data-user-link>\s*'.$admin->id.'\s*<\/a>/')
        ->toMatch('/<div class="table-field-main">'.preg_quote($admin->full_name, '/').'<\/div>/');
});
