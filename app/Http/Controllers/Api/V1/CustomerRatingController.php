<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Projects\CustomerRatingServiceInterface;
use App\Http\Requests\Api\V1\StoreCustomerRatingRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRatingRequest;
use App\Http\Resources\Api\V1\CustomerRatingResource;
use App\Models\Projects\CustomerRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerRatingController extends Controller
{
    public function __construct(
        private CustomerRatingServiceInterface $ratings,
    ) {}

    /**
     * List customer ratings (Odoo Project › Customer Ratings).
     *
     * @queryParam project_id int Filter by project. Example: 1
     * @queryParam rating int Filter by score 1-5. Example: 5
     * @queryParam search string Search comment. Example: excellent
     * @queryParam per_page int Default 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerRating::class);

        $query = CustomerRating::query()
            ->with(['project', 'contact', 'rateable', 'creator'])
            ->orderByDesc('rated_at')
            ->orderByDesc('id');

        if ($projectId = $request->integer('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($rating = $request->integer('rating')) {
            $query->where('rating', $rating);
        }
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('comment', 'like', '%'.$search.'%');
        }

        return CustomerRatingResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreCustomerRatingRequest $request): JsonResponse
    {
        $this->authorize('create', CustomerRating::class);

        $rating = $this->ratings->create($request->validated());

        return (new CustomerRatingResource($rating->load(['project', 'contact', 'rateable', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CustomerRating $customerRating): CustomerRatingResource
    {
        $this->authorize('view', $customerRating);

        return new CustomerRatingResource($customerRating->load(['project', 'contact', 'rateable', 'creator']));
    }

    public function update(UpdateCustomerRatingRequest $request, CustomerRating $customerRating): CustomerRatingResource
    {
        $this->authorize('update', $customerRating);

        return new CustomerRatingResource($this->ratings->update($customerRating, $request->validated()));
    }

    public function destroy(CustomerRating $customerRating): JsonResponse
    {
        $this->authorize('delete', $customerRating);

        $this->ratings->delete($customerRating);

        return $this->deleted('Rating pelanggan berhasil dihapus.');
    }
}
