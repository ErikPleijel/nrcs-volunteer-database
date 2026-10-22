<?php

/**
 * Useful links and Downloads on My Profile: two json settings
 * (profile.useful_links, profile.downloads) in the 'profile_resources'
 * group, edited by the National DB administrator on their own Settings
 * page. Uploaded files live on the private 'local' disk and are served to
 * logged-in users at /downloads/{id}.
 */

use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProfileResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const PDF_MIME = 'application/pdf';

beforeEach(function () {
    Cache::flush();
    Storage::fake('local');
    $this->withoutVite();

    foreach (['change_settings', 'manage-admin-panel', 'manage_roles_and_permissions'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')
        ->syncPermissions(['change_settings', 'manage-admin-panel', 'manage_roles_and_permissions']);
    // Can open edit-roles, but not Settings (matches PermissionsTableSeeder).
    Role::findOrCreate('branch_db_administrator', 'web')
        ->syncPermissions(['manage-admin-panel', 'manage_roles_and_permissions']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
});

function saveProfileResources(array $payload, ?User $user = null)
{
    return test()->actingAs($user ?? test()->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.profile-resources.update'), $payload);
}

/** The current links as form rows, for a save that changes nothing. */
function currentLinkRows(): array
{
    return ProfileResources::links();
}

function storedDownloads(): array
{
    return Setting::where('key', ProfileResources::DOWNLOADS_KEY)->first()->castValue();
}

test('the migration seeds the eight IFRC links in order, and no downloads', function () {
    $links = Setting::where('key', ProfileResources::LINKS_KEY)->first();
    $downloads = Setting::where('key', ProfileResources::DOWNLOADS_KEY)->first();

    expect($links->type)->toBe('json')
        ->and($links->group)->toBe(ProfileResources::GROUP)
        ->and(array_column(ProfileResources::links(), 'title'))->toBe([
            'Communities of Practice',
            'IFRC Learning Platform',
            'IFRC Jobs',
            'IFRC Official',
            'Red Hub Solutions',
            'IFRC LinkedIn',
            'IFRC GO Platform',
            'REDpreneur',
        ])
        ->and(ProfileResources::links()[4]['url'])->toBe('https://solutions.ifrc.org/')
        ->and(ProfileResources::links()[6]['url'])->toBe('https://go.ifrc.org/')
        ->and($downloads->type)->toBe('json')
        ->and(ProfileResources::downloads())->toBe([]);
});

test('the national DB administrator sees the editor; others cannot open or save it', function () {
    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.profile-resources.index'))
        ->assertOk()
        ->assertSee('Useful links')
        ->assertSee('Red Hub Solutions')
        ->assertSee('Add download');

    $branch = User::factory()->create();
    $branch->assignRole('branch_db_administrator');

    $this->actingAs($branch)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.profile-resources.index'))
        ->assertForbidden();
    saveProfileResources(['links' => []], $branch)->assertForbidden();

    expect(ProfileResources::links())->toHaveCount(8);
});

test('opening or saving the editor asks for the password first', function () {
    $this->actingAs($this->national)
        ->get(route('admin.settings.profile-resources.index'))
        ->assertRedirect(route('password.confirm'));
});

test('saving edited, reordered links stores them, clears the cache and is audited once', function () {
    ProfileResources::links(); // warm the cache

    saveProfileResources(['links' => [
        ['title' => ' IFRC GO ', 'url' => 'https://go.ifrc.org/', 'description' => 'Emergency operations'],
        ['title' => 'IFRC Official', 'url' => 'http://www.ifrc.org/', 'description' => ''],
    ]])->assertRedirect(route('admin.settings.profile-resources.index'))
        ->assertSessionHasNoErrors();

    expect(ProfileResources::links())->toBe([
        ['title' => 'IFRC GO', 'url' => 'https://go.ifrc.org/', 'description' => 'Emergency operations'],
        ['title' => 'IFRC Official', 'url' => 'http://www.ifrc.org/', 'description' => ''],
    ]);

    // Only the links changed: the empty downloads list is not audited.
    $logs = AuditLog::where('action', 'setting_changed')->get();
    expect($logs)->toHaveCount(1)
        ->and($logs[0]->description)->toBe('Setting "profile.useful_links" updated.')
        ->and($logs[0]->old_values['profile.useful_links'])->toHaveCount(8)
        ->and($logs[0]->new_values['profile.useful_links'][0]['title'])->toBe('IFRC GO');
});

test('saving without changes writes no audit entry', function () {
    saveProfileResources(['links' => currentLinkRows()])->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
});

test('links need a title of up to 80 characters and an http or https address', function (array $row, string $errorKey) {
    saveProfileResources(['links' => [$row]])->assertSessionHasErrors($errorKey);

    expect(ProfileResources::links())->toHaveCount(8)
        ->and(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
})->with([
    'no title' => [['title' => '', 'url' => 'https://ifrc.org'], 'links.0.title'],
    'title too long' => [['title' => str_repeat('a', 81), 'url' => 'https://ifrc.org'], 'links.0.title'],
    'no address' => [['title' => 'IFRC', 'url' => ''], 'links.0.url'],
    'javascript:' => [['title' => 'IFRC', 'url' => 'javascript:alert(1)'], 'links.0.url'],
    'data:' => [['title' => 'IFRC', 'url' => 'data:text/html,<script>alert(1)</script>'], 'links.0.url'],
    'ftp:' => [['title' => 'IFRC', 'url' => 'ftp://ifrc.org/file'], 'links.0.url'],
    'no scheme' => [['title' => 'IFRC', 'url' => 'www.ifrc.org'], 'links.0.url'],
]);

test('an uploaded file is stored privately and served to any logged-in user', function () {
    saveProfileResources([
        'links' => currentLinkRows(),
        'downloads' => [[
            'id' => '',
            'title' => 'Volunteer Diary',
            'source' => 'file',
            'file' => UploadedFile::fake()->create('Volunteer Diary 2026.pdf', 500, PDF_MIME),
            'description' => 'Log your hours',
        ]],
    ])->assertSessionHasNoErrors();

    [$download] = ProfileResources::downloads();
    expect($download['title'])->toBe('Volunteer Diary')
        ->and($download['url'])->toBeNull()
        ->and($download['file_name'])->toBe('Volunteer Diary 2026.pdf')
        ->and($download['path'])->toStartWith('profile-downloads/')
        ->and($download['id'])->not->toBe('');
    Storage::disk('local')->assertExists($download['path']);

    $member = User::factory()->create();
    $this->actingAs($member)
        ->get(route('downloads.show', $download['id']))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="Volunteer Diary 2026.pdf"');
});

test('Word and Excel files are downloaded rather than opened', function () {
    saveProfileResources(['downloads' => [[
        'id' => '', 'title' => 'Register', 'source' => 'file',
        'file' => UploadedFile::fake()->create('register.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    ]]])->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->create())
        ->get(route('downloads.show', ProfileResources::downloads()[0]['id']))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=register.xlsx');
});

test('guests are sent to log in, and unknown ids are not found', function () {
    saveProfileResources(['downloads' => [[
        'id' => '', 'title' => 'Diary', 'source' => 'file',
        'file' => UploadedFile::fake()->create('diary.pdf', 10, PDF_MIME),
    ]]]);
    $id = ProfileResources::downloads()[0]['id'];

    auth()->logout();
    $this->get(route('downloads.show', $id))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('downloads.show', 'no-such-id'))
        ->assertNotFound();
});

test('keeping a row keeps its file; replacing or deleting removes the old file', function () {
    saveProfileResources(['downloads' => [
        ['id' => '', 'title' => 'Diary', 'source' => 'file', 'file' => UploadedFile::fake()->create('diary.pdf', 10, PDF_MIME)],
        ['id' => '', 'title' => 'Handbook', 'source' => 'file', 'file' => UploadedFile::fake()->create('handbook.pdf', 10, PDF_MIME)],
    ]]);
    [$diary, $handbook] = ProfileResources::downloads();

    // Rename the diary (file kept), replace the handbook's file.
    saveProfileResources(['downloads' => [
        ['id' => $diary['id'], 'title' => 'Volunteer Diary', 'source' => 'file'],
        ['id' => $handbook['id'], 'title' => 'Handbook', 'source' => 'file', 'file' => UploadedFile::fake()->create('handbook-v2.pdf', 10, PDF_MIME)],
    ]])->assertSessionHasNoErrors();
    [$diaryAfter, $handbookAfter] = ProfileResources::downloads();

    expect($diaryAfter['id'])->toBe($diary['id'])
        ->and($diaryAfter['path'])->toBe($diary['path'])
        ->and($diaryAfter['title'])->toBe('Volunteer Diary')
        ->and($handbookAfter['id'])->toBe($handbook['id'])
        ->and($handbookAfter['file_name'])->toBe('handbook-v2.pdf');
    Storage::disk('local')->assertExists([$diary['path'], $handbookAfter['path']]);
    Storage::disk('local')->assertMissing($handbook['path']);

    // Delete the diary row; switch the handbook to an external address.
    saveProfileResources(['downloads' => [
        ['id' => $handbook['id'], 'title' => 'Handbook', 'source' => 'url', 'url' => 'https://www.ifrc.org/handbook.pdf'],
    ]])->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing([$diary['path'], $handbookAfter['path']]);
    expect(ProfileResources::downloads())->toHaveCount(1)
        ->and(ProfileResources::downloads()[0]['url'])->toBe('https://www.ifrc.org/handbook.pdf')
        ->and(ProfileResources::downloads()[0]['path'])->toBeNull();
});

test('downloads reject other file types, files over 20 MB and rows with nothing to download', function (array $row, string $errorKey) {
    saveProfileResources(['downloads' => [$row + ['id' => '', 'title' => 'Doc']]])->assertSessionHasErrors($errorKey);

    expect(ProfileResources::downloads())->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'text file' => [['source' => 'file', 'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')], 'downloads.0.file'],
    'php file' => [['source' => 'file', 'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')], 'downloads.0.file'],
    'pdf named .exe' => [['source' => 'file', 'file' => UploadedFile::fake()->create('diary.exe', 10, PDF_MIME)], 'downloads.0.file'],
    'over 20 MB' => [['source' => 'file', 'file' => UploadedFile::fake()->create('big.pdf', 20481, PDF_MIME)], 'downloads.0.file'],
    'no file' => [['source' => 'file'], 'downloads.0.file'],
    'no address' => [['source' => 'url', 'url' => ''], 'downloads.0.url'],
    'javascript:' => [['source' => 'url', 'url' => 'javascript:alert(1)'], 'downloads.0.url'],
    'no title' => [['source' => 'url', 'url' => 'https://ifrc.org', 'title' => ''], 'downloads.0.title'],
]);

test('a posted id cannot take over another row or keep a file it does not have', function () {
    saveProfileResources(['downloads' => [
        ['id' => '', 'title' => 'Diary', 'source' => 'file', 'file' => UploadedFile::fake()->create('diary.pdf', 10, PDF_MIME)],
    ]]);

    // An unknown id with no upload has no file to keep.
    saveProfileResources(['downloads' => [
        ['id' => 'made-up', 'title' => 'Other', 'source' => 'file'],
    ]])->assertSessionHasErrors('downloads.0.file');

    // A path in the form is ignored; an unknown id gets a new one.
    saveProfileResources(['downloads' => [
        ['id' => 'made-up', 'title' => 'Other', 'source' => 'url', 'url' => 'https://ifrc.org/', 'path' => '../.env'],
    ]])->assertSessionHasNoErrors();

    $stored = storedDownloads();
    expect($stored[0]['id'])->not->toBe('made-up')
        ->and($stored[0]['path'])->toBeNull();
});

test('the generic settings page neither shows nor saves these lists', function () {
    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertDontSee('settings[profile.useful_links]', false)
        ->assertDontSee('communities.ifrc.org');

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => ['profile.useful_links' => '[]']]);

    expect(ProfileResources::links())->toHaveCount(8);
});
