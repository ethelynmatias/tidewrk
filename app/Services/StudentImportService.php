<?php

namespace App\Services;


use App\Models\ImportBatch;
use App\Importers\CsvStudentImporter;
use Illuminate\Support\Facades\DB;


class StudentImportService
{
    public function __construct(private CsvStudentImporter $importer) {

    }

    public function import(ImportBatch $batch): object {
        return DB::transaction(function() use ($batch){
            return $this->importer->process(
                $batch->path
            );
        });
    }
}
