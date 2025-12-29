<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyProfileResource;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;

class PublicCompanyProfileController extends Controller
{
    /**
     * Get company profile by slug or custom domain.
     *
     * @operationId publicCompanyProfileShow
     */
    public function show(string $identifier): CompanyProfileResource|JsonResponse
    {
        $profile = CompanyProfile::findBySlugOrDomain($identifier);

        if (! $profile) {
            return response()->json([
                'message' => 'Profil perusahaan tidak ditemukan.',
                'error' => 'profile_not_found',
            ], 404);
        }

        return new CompanyProfileResource($profile);
    }

    /**
     * List all active company profiles (for directory/showcase page).
     *
     * @operationId publicCompanyProfileList
     */
    public function index(): JsonResponse
    {
        $profiles = CompanyProfile::active()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
                'tagline',
                'logo_path',
                'primary_color',
            ]);

        // Transform to include logo URLs
        $data = $profiles->map(function ($profile) {
            return [
                'id' => $profile->id,
                'name' => $profile->name,
                'slug' => $profile->slug,
                'tagline' => $profile->tagline,
                'logo_url' => $profile->logo_url,
                'primary_color' => $profile->primary_color,
                'public_url' => $profile->public_url,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
