<?php

namespace App\Repositories\Contracts;

interface StudentRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $students
     */
    public function upsertMany(array $students): void;
}
