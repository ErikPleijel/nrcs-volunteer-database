<?php

/**
 * The uploaded SG signature (sg-signature.png) is not in git, so a fresh
 * server has none: ID cards then use the placeholder that is in git.
 */

use App\Http\Controllers\IdSignatureController;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->originalPublic = public_path();
    $this->tmpPublic = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sg-sig-'.uniqid();
    File::ensureDirectoryExists($this->tmpPublic.'/images/id-card');
    app()->usePublicPath($this->tmpPublic);
});

afterEach(function () {
    app()->usePublicPath($this->originalPublic);
    File::deleteDirectory($this->tmpPublic);
});

test('without an uploaded signature the ID card uses the placeholder', function () {
    expect(IdSignatureController::signatureUrl())->toEndWith('/images/id-card/sg-signature-placeholder.png');
});

test('once a signature is uploaded the ID card uses it', function () {
    File::put($this->tmpPublic.'/images/id-card/sg-signature.png', 'png');

    expect(IdSignatureController::signatureUrl())->toEndWith('/images/id-card/sg-signature.png');
});

test('the placeholder is in the repo and the uploadable files are git-ignored', function () {
    $root = base_path();
    $tracked = shell_exec('git -C '.escapeshellarg($root).' ls-files public/images/id-card public/images/signatures');
    $ignored = shell_exec('git -C '.escapeshellarg($root).' check-ignore public/images/id-card/sg-signature.png public/images/signatures/Uploaded.png');

    expect($tracked)->toContain('public/images/id-card/sg-signature-placeholder.png')
        ->and($tracked)->not->toContain('public/images/id-card/sg-signature.png')
        ->and($tracked)->not->toContain('public/images/signatures/Picture1.png')
        ->and($ignored)->toContain('public/images/id-card/sg-signature.png')
        ->and($ignored)->toContain('public/images/signatures/Uploaded.png');
});
