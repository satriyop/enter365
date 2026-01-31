<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyProfileService
{
    /**
     * Create a new company profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CompanyProfile
    {
        // Handle logo upload
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo_path'] = $data['logo']->store('company-profiles/logos', 'public');
            unset($data['logo']);
        }

        // Handle cover image upload
        if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image_path'] = $data['cover_image']->store('company-profiles/covers', 'public');
            unset($data['cover_image']);
        }

        return CompanyProfile::create($data);
    }

    /**
     * Update a company profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyProfile $profile, array $data): CompanyProfile
    {
        // Handle logo upload - upload first, then delete old to prevent data loss
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $oldLogoPath = $profile->logo_path;
            $newPath = $data['logo']->store('company-profiles/logos', 'public');
            if ($newPath) {
                $data['logo_path'] = $newPath;
                if ($oldLogoPath) {
                    Storage::disk('public')->delete($oldLogoPath);
                }
            }
            unset($data['logo']);
        }

        // Handle cover image upload - upload first, then delete old to prevent data loss
        if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $oldCoverPath = $profile->cover_image_path;
            $newPath = $data['cover_image']->store('company-profiles/covers', 'public');
            if ($newPath) {
                $data['cover_image_path'] = $newPath;
                if ($oldCoverPath) {
                    Storage::disk('public')->delete($oldCoverPath);
                }
            }
            unset($data['cover_image']);
        }

        $profile->update($data);

        return $profile->fresh();
    }

    /**
     * Delete a company profile and its associated files.
     */
    public function delete(CompanyProfile $profile): void
    {
        // Delete associated images
        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
        }
        if ($profile->cover_image_path) {
            Storage::disk('public')->delete($profile->cover_image_path);
        }

        $profile->delete();
    }

    /**
     * Remove the logo image from a company profile.
     */
    public function removeLogo(CompanyProfile $profile): CompanyProfile
    {
        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }

        return $profile->fresh();
    }

    /**
     * Remove the cover image from a company profile.
     */
    public function removeCover(CompanyProfile $profile): CompanyProfile
    {
        if ($profile->cover_image_path) {
            Storage::disk('public')->delete($profile->cover_image_path);
            $profile->update(['cover_image_path' => null]);
        }

        return $profile->fresh();
    }
}
