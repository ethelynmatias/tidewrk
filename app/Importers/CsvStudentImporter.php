<?php

namespace App\Importers;

use App\Repositories\Contracts\SchoolRepositoryInterface;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

class CsvStudentImporter
{
    private const CHUNK_SIZE = 500;
    private array $schoolMap = [];
    private int $studentsCreated = 0;

    public function __construct(
        private SchoolRepositoryInterface $schools,
        private StudentRepositoryInterface $students) {
    }

    public function process(string $path): object {
        $this->stream(
            Storage::disk('local')->path($path)
        );

        return (object)[
            'schools' =>count($this->schoolMap),
            'students' =>$this->studentsCreated
        ];
    }

    private function stream(string $filePath):void {

        $file = new SplFileObject($filePath);

        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );

        $header = null;
        $rows=[];

        foreach($file as $row) {

            if(! is_array($row)){
                continue;
            }

            if($header === null){
                $header =
                    array_flip(
                        array_map(
                            'trim',
                            $row
                        )
                    );
                continue;
            }

            $rows[]=$row;

            if(count($rows)>=self::CHUNK_SIZE) {
                $this->flush($header,$rows);
                $rows=[];
            }
        }

        if($rows) {
            $this->flush($header,$rows);
        }
    }

    private function flush(array $header,array $rows):void {
        $this->importSchools(
            $header,
            $rows
        );

        $this->importStudents(
            $header,
            $rows
        );
    }

    private function importSchools(array $header, array $rows):void {

        $newSchools = [];

        foreach($rows as $row){

            $code = $this->value($header, $row, 'school_code');

            if(
                $code === '' ||
                isset($this->schoolMap[$code]) ||
                isset($newSchools[$code])
            ){
                continue;
            }

            $newSchools[$code] = [
                'code'       => $code,
                'name'       => $this->value($header, $row, 'school_name'),
                'created_at' => now(),
                'updated_at' => now()
            ];

        }

        if(! $newSchools){
            return;
        }


        $this->schools->upsertMany(
            array_values($newSchools)
        );


        $this->schoolMap += $this->schools->idMapByCodes(
            array_keys($newSchools)
        );
    }

    private function importStudents(array $header,array $rows):void {

        $students = [];


        foreach($rows as $row){

            $schoolId = $this->schoolMap[
                $this->value($header, $row, 'school_code')
            ] ?? null;

            if($schoolId === null){
                continue;
            }

            $students[] = [
                'school_id'     => $schoolId,
                'student_id'    => $this->value($header, $row, 'student_id'),
                'student_code'  => $this->value($header, $row, 'student_code'),
                'first_name'    => $this->value($header, $row, 'first_name'),
                'last_name'     => $this->value($header, $row, 'last_name'),
                'date_of_birth' => Carbon::parse(
                    $this->value($header, $row, 'date_of_birth')
                )->toDateString(),
                'created_at'    => now(),
                'updated_at'    => now()
            ];

        }

        if(! $students){
            return;
        }

        $this->students->upsertMany($students);
        $this->studentsCreated += count($students);
    }

    private function value(
        array $header,
        array $row,
        string $column
    ):string
    {

    $index = $header[$column] ?? null;


    return $index !== null
        ? trim((string)($row[$index] ?? ''))
        : '';

    }
}
