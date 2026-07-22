<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;
use App\Jobs\StudentImportJob;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(UploadRequest $request): JsonResponse
    {
        $path  = $request->file('file')->store('imports');
        $batch = ImportBatch::create(['status' => ImportStatus::Queued, 'path' => $path]);

        StudentImportJob::dispatch($batch);

        return response()->json([
            'message'  => 'Import queued successfully.',
            'batch_id' => $batch->id,
        ], 202);
    }
}
