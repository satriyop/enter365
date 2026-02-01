<?php

declare(strict_types=1);

use App\Models\Shared\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
});

describe('Attachment authorization - unauthenticated', function () {
    it('unauthenticated user cannot access attachments', function () {
        $this->getJson('/api/v1/attachments')->assertUnauthorized();
    });
});

describe('Attachment authorization - authenticated access', function () {
    it('authenticated user can list attachments', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/attachments')->assertSuccessful();
    });

    it('authenticated user can view an attachment', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attachment = Attachment::factory()->create(['uploaded_by' => $user->id]);

        $response = $this->getJson("/api/v1/attachments/{$attachment->id}");
        // Authorization passes — response is not 401/403
        expect($response->status())->not->toBeIn([401, 403]);
    });
});

describe('Attachment authorization - delete ownership', function () {
    it('uploader can delete their own attachment', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attachment = Attachment::factory()->create(['uploaded_by' => $user->id]);

        $this->deleteJson("/api/v1/attachments/{$attachment->id}")->assertSuccessful();
    });

    it('non-uploader cannot delete another users attachment', function () {
        $uploader = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $attachment = Attachment::factory()->create(['uploaded_by' => $uploader->id]);

        $this->deleteJson("/api/v1/attachments/{$attachment->id}")->assertForbidden();
    });

    it('admin can delete any attachment', function () {
        $uploader = User::factory()->create();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $attachment = Attachment::factory()->create(['uploaded_by' => $uploader->id]);

        $this->deleteJson("/api/v1/attachments/{$attachment->id}")->assertSuccessful();
    });
});
