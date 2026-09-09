<?php

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publicProfile(array $overrides = []): CompanyProfile
{
    return CompanyProfile::query()->create(array_merge([
        'slug' => 'nex',
        'name' => 'NEX Solar',
        'tagline' => 'Energi masa depan',
        'description' => 'Public marketing copy',
        'logo_path' => 'company-profiles/nex-logo.png',
        'cover_image_path' => 'company-profiles/nex-cover.png',
        'founded_year' => 2018,
        'employees_count' => '10-50',
        'primary_color' => '#0f766e',
        'email' => 'info@energimasadepan.com',
        'phone' => '021000',
        'address' => 'Jakarta',
        'website' => 'https://energimasadepan.com',
        'custom_domain' => null,
        'is_active' => true,
    ], $overrides));
}

it('returns marketing fields on the public show without admin paths or team', function () {
    publicProfile(['custom_domain' => 'secret.example.test']);

    $response = $this->getJson('/api/v1/public/company-profiles/nex');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'nex')
        ->assertJsonPath('data.email', 'info@energimasadepan.com')
        ->assertJsonPath('data.name', 'NEX Solar');

    $payload = $response->json('data');
    expect($payload)->toHaveKeys(['logo_url', 'public_url', 'cover_image_url'])
        ->and($payload)->not->toHaveKey('logo_path')
        ->and($payload)->not->toHaveKey('cover_image_path')
        ->and($payload)->not->toHaveKey('team')
        ->and($payload)->not->toHaveKey('custom_domain')
        ->and($payload)->not->toHaveKey('is_active')
        ->and($payload)->not->toHaveKey('created_at')
        ->and($payload)->not->toHaveKey('updated_at');
});

it('returns profile_not_found for an unknown slug', function () {
    $this->getJson('/api/v1/public/company-profiles/missing')
        ->assertNotFound()
        ->assertJsonPath('error', 'profile_not_found');
});

it('returns 404 for an inactive profile', function () {
    publicProfile(['slug' => 'hidden', 'is_active' => false]);

    $this->getJson('/api/v1/public/company-profiles/hidden')
        ->assertNotFound()
        ->assertJsonPath('error', 'profile_not_found');
});

it('lists only active profiles in the public directory', function () {
    publicProfile(['slug' => 'nex']);
    publicProfile(['slug' => 'vahana', 'name' => 'Vahana', 'email' => 'vahana@example.test']);
    publicProfile(['slug' => 'hidden', 'name' => 'Hidden', 'is_active' => false]);

    $response = $this->getJson('/api/v1/public/company-profiles');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('nex')
        ->and($slugs)->toContain('vahana')
        ->and($slugs)->not->toContain('hidden');

    $first = $response->json('data.0');
    expect($first)->toHaveKeys(['id', 'name', 'slug', 'tagline', 'logo_url', 'primary_color', 'public_url'])
        ->and($first)->not->toHaveKey('logo_path')
        ->and($first)->not->toHaveKey('team')
        ->and($first)->not->toHaveKey('custom_domain');
});

it('keeps logo_path on the authenticated admin show', function () {
    $profile = publicProfile(['custom_domain' => 'secret.example.test']);
    authenticatedAdmin();

    $this->getJson('/api/v1/company-profiles/'.$profile->id)
        ->assertOk()
        ->assertJsonPath('data.logo_path', 'company-profiles/nex-logo.png')
        ->assertJsonPath('data.custom_domain', 'secret.example.test');
});
