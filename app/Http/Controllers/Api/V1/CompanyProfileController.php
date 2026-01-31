<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\CompanyProfileFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyProfileRequest;
use App\Http\Requests\Api\V1\UpdateCompanyProfileRequest;
use App\Http\Resources\Api\V1\CompanyProfileResource;
use App\Models\CompanyProfile;
use App\Services\Shared\CompanyProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyProfileController extends Controller
{
    public function __construct(
        private CompanyProfileService $profileService
    ) {}

    public function index(CompanyProfileFilter $filter): AnonymousResourceCollection
    {
        $profiles = CompanyProfile::query()
            ->filter($filter)
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return CompanyProfileResource::collection($profiles);
    }

    public function store(StoreCompanyProfileRequest $request): CompanyProfileResource
    {
        $profile = $this->profileService->create($request->validated());

        return new CompanyProfileResource($profile);
    }

    public function show(CompanyProfile $companyProfile, CompanyProfileFilter $filter): CompanyProfileResource
    {
        $filter->apply($companyProfile->newQuery());

        return new CompanyProfileResource($companyProfile);
    }

    public function update(UpdateCompanyProfileRequest $request, CompanyProfile $companyProfile): CompanyProfileResource
    {
        $profile = $this->profileService->update($companyProfile, $request->validated());

        return new CompanyProfileResource($profile);
    }

    public function destroy(CompanyProfile $companyProfile): JsonResponse
    {
        $this->profileService->delete($companyProfile);

        return response()->json(['message' => 'Profil perusahaan berhasil dihapus.']);
    }

    /**
     * Remove the logo image.
     */
    public function removeLogo(CompanyProfile $companyProfile): CompanyProfileResource
    {
        $profile = $this->profileService->removeLogo($companyProfile);

        return new CompanyProfileResource($profile);
    }

    /**
     * Remove the cover image.
     */
    public function removeCover(CompanyProfile $companyProfile): CompanyProfileResource
    {
        $profile = $this->profileService->removeCover($companyProfile);

        return new CompanyProfileResource($profile);
    }
}
