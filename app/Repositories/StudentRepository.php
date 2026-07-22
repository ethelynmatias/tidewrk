<?php

namespace App\Repositories;

use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;

class StudentRepository implements StudentRepositoryInterface
{
    public function upsertMany(array $students): void
    {
        if ($students === []) {
            return;
        }

        // Deduplicated by `student_code`; re-uploads update in place.
        Student::upsert(
            $students,
            ['student_code'],
            ['school_id', 'student_id', 'first_name', 'last_name', 'date_of_birth', 'updated_at']
        );
    }
}
