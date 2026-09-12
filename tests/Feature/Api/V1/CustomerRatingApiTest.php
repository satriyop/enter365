<?php

use App\Models\Contacts\Contact;
use App\Models\Projects\CustomerRating;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Customer ratings (#154)', function () {
    it('creates a project rating and lists it', function () {
        $project = Project::factory()->create();
        $contact = Contact::factory()->customer()->create();

        $create = $this->postJson('/api/v1/customer-ratings', [
            'project_id' => $project->id,
            'rateable_type' => 'project',
            'rateable_id' => $project->id,
            'contact_id' => $contact->id,
            'rating' => 5,
            'comment' => 'Excellent delivery.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.rateable_type', 'project');

        $this->getJson('/api/v1/customer-ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.comment', 'Excellent delivery.');
    });

    it('creates a task rating and rejects a task from another project', function () {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $task = Task::factory()->forProject($project)->create();
        $foreign = Task::factory()->forProject($other)->create();

        $this->postJson('/api/v1/customer-ratings', [
            'project_id' => $project->id,
            'rateable_type' => 'task',
            'rateable_id' => $task->id,
            'rating' => 4,
        ])->assertCreated()->assertJsonPath('data.rateable_type', 'task');

        $this->postJson('/api/v1/customer-ratings', [
            'project_id' => $project->id,
            'rateable_type' => 'task',
            'rateable_id' => $foreign->id,
            'rating' => 3,
        ])->assertStatus(409);
    });

    it('updates and deletes a rating', function () {
        $project = Project::factory()->create();
        $rating = CustomerRating::factory()->forProject($project)->create(['rating' => 2]);

        $this->putJson('/api/v1/customer-ratings/'.$rating->id, [
            'rating' => 4,
            'comment' => 'Improved.',
        ])->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Improved.');

        $this->deleteJson('/api/v1/customer-ratings/'.$rating->id)->assertOk();
        expect(CustomerRating::query()->whereKey($rating->id)->exists())->toBeFalse();
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/customer-ratings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'rating']);
    });

    it('returns customer ratings analysis with averages', function () {
        $project = Project::factory()->create();
        CustomerRating::factory()->forProject($project)->create(['rating' => 5]);
        CustomerRating::factory()->forProject($project)->create(['rating' => 3]);

        $this->getJson('/api/v1/reports/customer-ratings')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Customer Ratings')
            ->assertJsonPath('data.totals.count', 2)
            ->assertJsonPath('data.totals.average', 4);
    });
});
