<?php

namespace App\Repositories;

use App\Models\School;
use App\Repositories\Contracts\SchoolRepositoryInterface;

class SchoolRepository implements SchoolRepositoryInterface
{
    public function upsertMany(array $schools): void
    {
        if ($schools === []) {
            return;
        }

        // Unique school is inserted only once.
        School::upsert($schools, ['code'], ['name', 'updated_at']);
    }

    public function idMapByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return School::whereIn('code', $codes)
            ->pluck('id', 'code')
            ->all();
    }
}
