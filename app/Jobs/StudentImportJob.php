<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class StudentImportJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600;

    public function __construct(private readonly ImportBatch $batch) {

    }

    public function handle(StudentImportService $service): void {

        $this->batch->update(['status' => ImportStatus::Processing]);

        try {

            $result = $service->import($this->batch);

            $this->batch->update([
                'status' => ImportStatus::Completed,
                'schools_created' =>$result->schools,
                'students_created' =>$result->students,
            ]);

        } catch(Throwable $exception) {

            $this->batch->update([
                'status' => ImportStatus::Failed,
                'errors' => ['message' =>$exception->getMessage()]
            ]);

            throw $exception;
        }
    }
}
