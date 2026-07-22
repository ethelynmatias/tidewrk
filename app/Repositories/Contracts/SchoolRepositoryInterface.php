<?php

namespace App\Repositories\Contracts;

interface SchoolRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $schools
     */
    public function upsertMany(array $schools): void;

    /**
     * @param  array<int, string>  $codes
     * @return array<string, int>
     */
    public function idMapByCodes(array $codes): array;
}
