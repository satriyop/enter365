<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Filters\ProjectFilter;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectCostResource;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\ProjectRevenueResource;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Projects\ProjectRevenue;
use App\Models\Sales\Quotation;
use App\Services\Projects\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService
    ) {}

    /**
     * Display a listing of projects.
     */
    public function index(ProjectFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->with(['contact', 'manager']) // Default eager loads
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projectService->create($request->validated());

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project, ProjectFilter $filter): ProjectResource
    {
        $this->authorize('view', $project);

        $filter->apply($project->newQuery());

        $project->loadMissing(['contact', 'manager', 'quotation', 'costs', 'revenues', 'creator']);

        return new ProjectResource($project);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project = $this->projectService->update($project, $request->validated());

        return new ProjectResource($project);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->delete($project);

        return response()->json(['message' => 'Proyek berhasil dihapus.']);
    }

    /**
     * Create project from quotation.
     */
    public function createFromQuotation(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('create', Project::class);

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'budget_amount' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority' => ['nullable', 'string', Rule::in(array_keys(Project::getPriorities()))],
            'location' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! in_array($quotation->status, [DocumentStatus::Approved, DocumentStatus::Converted])) {
            return response()->json([
                'message' => 'Hanya penawaran yang sudah disetujui yang dapat dibuat menjadi proyek.',
            ], 422);
        }

        $project = $this->projectService->createFromQuotation($quotation, $request->all());

        return response()->json([
            'message' => 'Proyek berhasil dibuat dari penawaran.',
            'data' => new ProjectResource($project),
        ], 201);
    }

    /**
     * Start a project.
     */
    public function start(Project $project): ProjectResource|JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $project = $this->projectService->start($project);

            return new ProjectResource($project);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Put project on hold.
     */
    public function hold(Request $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $project = $this->projectService->putOnHold($project, $request->input('reason'));

        return new ProjectResource($project);
    }

    /**
     * Resume a project.
     */
    public function resume(Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project = $this->projectService->resume($project);

        return new ProjectResource($project);
    }

    /**
     * Complete a project.
     */
    public function complete(Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project = $this->projectService->complete($project);

        return new ProjectResource($project);
    }

    /**
     * Cancel a project.
     */
    public function cancel(Request $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $project = $this->projectService->cancel($project, $request->input('reason'));

        return new ProjectResource($project);
    }

    /**
     * Update project progress.
     */
    public function updateProgress(Request $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $request->validate([
            'progress' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'progress.required' => 'Persentase progress harus diisi.',
            'progress.min' => 'Persentase progress minimal 0%.',
            'progress.max' => 'Persentase progress maksimal 100%.',
        ]);

        $project = $this->projectService->updateProgress($project, $request->input('progress'));

        return new ProjectResource($project);
    }

    /**
     * Add cost to project.
     */
    public function addCost(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ProjectCost::getCostTypes()))],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'type.required' => 'Tipe biaya harus diisi.',
            'description.required' => 'Deskripsi biaya harus diisi.',
            'unit_cost.required' => 'Biaya satuan harus diisi.',
        ]);

        $data = $request->all();
        $data['cost_type'] = $data['type'] ?? null;
        $data['cost_date'] = $data['date'] ?? null;

        $cost = $this->projectService->addCost($project, $data);

        return response()->json([
            'message' => 'Biaya berhasil ditambahkan.',
            'data' => new ProjectCostResource($cost),
        ], 201);
    }

    /**
     * Update project cost.
     */
    public function updateCost(Request $request, Project $project, ProjectCost $cost): JsonResponse
    {
        $this->authorize('update', $project);

        if ($cost->project_id !== $project->id) {
            return response()->json(['message' => 'Biaya tidak ditemukan untuk proyek ini.'], 404);
        }

        $request->validate([
            'type' => ['nullable', 'string', Rule::in(array_keys(ProjectCost::getCostTypes()))],
            'description' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data = $request->all();
        if (isset($data['type'])) {
            $data['cost_type'] = $data['type'];
        }
        if (isset($data['date'])) {
            $data['cost_date'] = $data['date'];
        }

        $cost = $this->projectService->updateCost($cost, $data);

        return response()->json([
            'message' => 'Biaya berhasil diperbarui.',
            'data' => new ProjectCostResource($cost),
        ]);
    }

    /**
     * Delete project cost.
     */
    public function deleteCost(Project $project, ProjectCost $cost): JsonResponse
    {
        $this->authorize('update', $project);

        if ($cost->project_id !== $project->id) {
            return response()->json(['message' => 'Biaya tidak ditemukan untuk proyek ini.'], 404);
        }

        $this->projectService->deleteCost($cost);

        return response()->json(['message' => 'Biaya berhasil dihapus.']);
    }

    /**
     * Add revenue to project.
     */
    public function addRevenue(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ProjectRevenue::getRevenueTypes()))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'type.required' => 'Tipe pendapatan harus diisi.',
            'description.required' => 'Deskripsi pendapatan harus diisi.',
            'amount.required' => 'Jumlah pendapatan harus diisi.',
        ]);

        $data = $request->all();
        $data['revenue_type'] = $data['type'] ?? null;
        $data['revenue_date'] = $data['date'] ?? null;

        $revenue = $this->projectService->addRevenue($project, $data);

        return response()->json([
            'message' => 'Pendapatan berhasil ditambahkan.',
            'data' => new ProjectRevenueResource($revenue),
        ], 201);
    }

    /**
     * Update project revenue.
     */
    public function updateRevenue(Request $request, Project $project, ProjectRevenue $revenue): JsonResponse
    {
        $this->authorize('update', $project);

        if ($revenue->project_id !== $project->id) {
            return response()->json(['message' => 'Pendapatan tidak ditemukan untuk proyek ini.'], 404);
        }

        $request->validate([
            'type' => ['nullable', 'string', Rule::in(array_keys(ProjectRevenue::getRevenueTypes()))],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data = $request->all();
        if (isset($data['type'])) {
            $data['revenue_type'] = $data['type'];
        }
        if (isset($data['date'])) {
            $data['revenue_date'] = $data['date'];
        }

        $revenue = $this->projectService->updateRevenue($revenue, $data);

        return response()->json([
            'message' => 'Pendapatan berhasil diperbarui.',
            'data' => new ProjectRevenueResource($revenue),
        ]);
    }

    /**
     * Delete project revenue.
     */
    public function deleteRevenue(Project $project, ProjectRevenue $revenue): JsonResponse
    {
        $this->authorize('update', $project);

        if ($revenue->project_id !== $project->id) {
            return response()->json(['message' => 'Pendapatan tidak ditemukan untuk proyek ini.'], 404);
        }

        $this->projectService->deleteRevenue($revenue);

        return response()->json(['message' => 'Pendapatan berhasil dihapus.']);
    }

    /**
     * Get project summary.
     */
    public function summary(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $summary = $this->projectService->getSummary($project->load(['costs', 'revenues']));

        return response()->json(['data' => $summary]);
    }

    /**
     * Get project statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $statistics = $this->projectService->getStatistics(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json(['data' => $statistics]);
    }
}
