<?php

declare(strict_types=1);

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePublicProfile(array $overrides = []): CompanyProfile
{
    return CompanyProfile::query()->create(array_merge([
        'slug' => 'nex',
        'name' => 'PT Nusantara Energi Khatulistiwa',
        'tagline' => 'Energi Masa Depan',
        'description' => 'PLTS atap.',
        'logo_path' => 'company-profiles/logos/nex.png',
        'cover_image_path' => 'company-profiles/covers/nex.png',
        'founded_year' => 2021,
        'employees_count' => '10-50',
        'primary_color' => '#22C55E',
        'secondary_color' => '#166534',
        'services' => [],
        'portfolio' => [],
        'certifications' => [],
        'social_links' => [],
        'email' => 'info@energimasadepan.com',
        'phone' => '021 59577680',
        'address' => 'Tangerang',
        'website' => 'https://energimasadepan.com',
        'custom_domain' => 'energimasadepan.com',
        'is_active' => true,
    ], $overrides));
}

it('returns a marketing profile without admin or team fields', function (): void {
    makePublicProfile();

    $response = $this->getJson('/api/v1/public/company-profiles/nex')
        ->assertOk();

    $data = $response->json('data');

    expect($data['slug'])->toBe('nex')
        ->and($data['name'])->toBe('PT Nusantara Energi Khatulistiwa')
        ->and($data)->toHaveKey('logo_url')
        ->and($data)->toHaveKey('public_url')
        ->and($data)->not->toHaveKey('logo_path')
        ->and($data)->not->toHaveKey('cover_image_path')
        ->and($data)->not->toHaveKey('team')
        ->and($data)->not->toHaveKey('custom_domain')
        ->and($data)->not->toHaveKey('is_active')
        ->and($data)->not->toHaveKey('created_at')
        ->and($data)->not->toHaveKey('updated_at');
});

it('returns 404 for an unknown slug', function (): void {
    $this->getJson('/api/v1/public/company-profiles/missing')
        ->assertNotFound()
        ->assertJsonPath('error', 'profile_not_found');
});

it('returns 404 for an inactive profile', function (): void {
    makePublicProfile(['slug' => 'hidden', 'is_active' => false]);

    $this->getJson('/api/v1/public/company-profiles/hidden')
        ->assertNotFound()
        ->assertJsonPath('error', 'profile_not_found');
});

it('lists only active profiles without expanding to the admin resource', function (): void {
    makePublicProfile(['slug' => 'nex', 'name' => 'NEX']);
    makePublicProfile([
        'slug' => 'hidden',
        'name' => 'Hidden Co',
        'is_active' => false,
        'custom_domain' => null,
    ]);

    $response = $this->getJson('/api/v1/public/company-profiles')
        ->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['slug'])->toBe('nex')
        ->and($data[0])->toHaveKeys(['id', 'name', 'slug', 'tagline', 'logo_url', 'primary_color', 'public_url'])
        ->and($data[0])->not->toHaveKey('logo_path')
        ->and($data[0])->not->toHaveKey('team');
});

it('keeps storage paths on the authenticated admin show', function (): void {
    $profile = makePublicProfile();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/company-profiles/'.$profile->id)
        ->assertOk()
        ->assertJsonPath('data.logo_path', 'company-profiles/logos/nex.png')
        ->assertJsonPath('data.custom_domain', 'energimasadepan.com');
});
