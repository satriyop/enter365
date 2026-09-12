<?php

declare(strict_types=1);

namespace App\Contracts\Projects;

use App\Models\Projects\CustomerRating;

interface CustomerRatingServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomerRating;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerRating $rating, array $data): CustomerRating;

    public function delete(CustomerRating $rating): void;
}
