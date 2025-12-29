<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyProfileRequest;
use App\Http\Requests\Api\V1\UpdateCompanyProfileRequest;
use App\Http\Resources\Api\V1\CompanyProfileResource;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CompanyProfileController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CompanyProfile::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhere('tagline', 'LIKE', "%{$search}%");
            });
        }

        $profiles = $query->orderBy('name')->paginate($request->input('per_page', 25));

        return CompanyProfileResource::collection($profiles);
    }

    public function store(StoreCompanyProfileRequest $request): CompanyProfileResource
    {
        $data = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-profiles/logos', 'public');
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('company-profiles/covers', 'public');
        }

        // Remove file keys from data (they're handled above)
        unset($data['logo'], $data['cover_image']);

        $profile = CompanyProfile::create($data);

        return new CompanyProfileResource($profile);
    }

    public function show(CompanyProfile $companyProfile): CompanyProfileResource
    {
        return new CompanyProfileResource($companyProfile);
    }

    public function update(UpdateCompanyProfileRequest $request, CompanyProfile $companyProfile): CompanyProfileResource
    {
        $data = $request->validated();

        // Handle logo upload - upload first, then delete old to prevent data loss
        if ($request->hasFile('logo')) {
            $oldLogoPath = $companyProfile->logo_path;
            $newPath = $request->file('logo')->store('company-profiles/logos', 'public');
            if ($newPath) {
                $data['logo_path'] = $newPath;
                if ($oldLogoPath) {
                    Storage::disk('public')->delete($oldLogoPath);
                }
            }
        }

        // Handle cover image upload - upload first, then delete old to prevent data loss
        if ($request->hasFile('cover_image')) {
            $oldCoverPath = $companyProfile->cover_image_path;
            $newPath = $request->file('cover_image')->store('company-profiles/covers', 'public');
            if ($newPath) {
                $data['cover_image_path'] = $newPath;
                if ($oldCoverPath) {
                    Storage::disk('public')->delete($oldCoverPath);
                }
            }
        }

        // Remove file keys from data
        unset($data['logo'], $data['cover_image']);

        $companyProfile->update($data);

        return new CompanyProfileResource($companyProfile->fresh());
    }

    public function destroy(CompanyProfile $companyProfile): JsonResponse
    {
        // Delete associated images
        if ($companyProfile->logo_path) {
            Storage::disk('public')->delete($companyProfile->logo_path);
        }
        if ($companyProfile->cover_image_path) {
            Storage::disk('public')->delete($companyProfile->cover_image_path);
        }

        $companyProfile->delete();

        return response()->json(['message' => 'Profil perusahaan berhasil dihapus.']);
    }

    /**
     * Remove the logo image.
     */
    public function removeLogo(CompanyProfile $companyProfile): CompanyProfileResource
    {
        if ($companyProfile->logo_path) {
            Storage::disk('public')->delete($companyProfile->logo_path);
            $companyProfile->update(['logo_path' => null]);
        }

        return new CompanyProfileResource($companyProfile->fresh());
    }

    /**
     * Remove the cover image.
     */
    public function removeCover(CompanyProfile $companyProfile): CompanyProfileResource
    {
        if ($companyProfile->cover_image_path) {
            Storage::disk('public')->delete($companyProfile->cover_image_path);
            $companyProfile->update(['cover_image_path' => null]);
        }

        return new CompanyProfileResource($companyProfile->fresh());
    }
}
