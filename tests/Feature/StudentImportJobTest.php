<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\StudentImportJob;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CSV where two schools are shared across five students.
     */
    private function sampleCsv(): string
    {
        return <<<CSV
        student_id,student_code,first_name,last_name,date_of_birth,school_code,school_name
        10001,STU-001,Bart,Simpson,2010-04-01,SCH-001,Springfield High
        10002,STU-002,Lisa,Simpson,2012-05-09,SCH-001,Springfield High
        10003,STU-003,Milhouse,Van Houten,2010-07-01,SCH-002,Shelbyville High
        10004,STU-004,Nelson,Muntz,2009-02-11,SCH-002,Shelbyville High
        10005,STU-005,Ralph,Wiggum,2011-03-15,SCH-001,Springfield High
        CSV;
    }

    /**
     * Store the CSV on the fake local disk and return a queued batch for it.
     */
    private function makeBatch(string $csv): ImportBatch
    {
        $path = 'imports/students.csv';
        Storage::disk('local')->put($path, $csv);

        return ImportBatch::create([
            'status' => ImportStatus::Queued,
            'path'   => $path,
        ]);
    }

    public function test_it_imports_schools_once_and_links_students(): void
    {
        Storage::fake('local');
        $batch = $this->makeBatch($this->sampleCsv());

        StudentImportJob::dispatchSync($batch);

        // Schools deduplicated — 5 rows, 2 distinct schools.
        $this->assertSame(2, School::count());
        // Every student imported.
        $this->assertSame(5, Student::count());
    }

    public function test_students_are_linked_to_the_correct_school(): void
    {
        Storage::fake('local');
        $batch = $this->makeBatch($this->sampleCsv());

        StudentImportJob::dispatchSync($batch);

        $springfield = School::where('code', 'SCH-001')->firstOrFail();
        $shelbyville = School::where('code', 'SCH-002')->firstOrFail();

        $this->assertSame(3, $springfield->students()->count());
        $this->assertSame(2, $shelbyville->students()->count());

        // No student left unlinked.
        $this->assertSame(0, Student::whereNull('school_id')->count());

        $this->assertDatabaseHas('students', [
            'student_code' => 'STU-001',
            'school_id'    => $springfield->id,
        ]);
    }

    public function test_it_marks_the_batch_completed_with_counts(): void
    {
        Storage::fake('local');
        $batch = $this->makeBatch($this->sampleCsv());

        StudentImportJob::dispatchSync($batch);

        $batch->refresh();

        $this->assertSame(ImportStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->schools_created);
        $this->assertSame(5, $batch->students_created);
    }

    public function test_re_running_the_import_does_not_create_duplicates(): void
    {
        Storage::fake('local');
        $batch = $this->makeBatch($this->sampleCsv());

        StudentImportJob::dispatchSync($batch);
        // Second run of the same data — must be idempotent.
        StudentImportJob::dispatchSync($this->makeBatch($this->sampleCsv()));

        $this->assertSame(2, School::count());
        $this->assertSame(5, Student::count());
    }

    public function test_it_marks_the_batch_failed_when_the_file_is_missing(): void
    {
        Storage::fake('local');

        $batch = ImportBatch::create([
            'status' => ImportStatus::Queued,
            'path'   => 'imports/does-not-exist.csv',
        ]);

        try {
            StudentImportJob::dispatchSync($batch);
        } catch (\Throwable) {
            // The job re-throws so the queue can mark it failed.
        }

        $batch->refresh();

        $this->assertSame(ImportStatus::Failed, $batch->status);
        $this->assertArrayHasKey('message', $batch->errors);
    }
}
