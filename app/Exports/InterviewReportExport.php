<?php

namespace App\Exports;

use App\Models\Interview;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InterviewReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $start;

    protected $end;

    public function __construct($startDate, $endDate)
    {
        $this->start = $startDate;
        $this->end = $endDate;
    }

    public function query()
    {
        return Interview::whereBetween('created_at', [$this->start, $this->end])
            ->orderBy('created_at');
    }

    public function map($interview): array
    {
        return [
            $interview->id,
            $interview->candidate_name,
            $interview->job_position,
            $interview->interview_status,
            $interview->interview_date,
            $interview->start_time,
            $interview->end_time,
            $interview->interview_mode,
            $interview->interviewer_name,
            $interview->reference_info,
            $interview->hired_status,
            $interview->feedback,
            $interview->score,
            $interview->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Candidate Name',
            'Position Applied',
            'Status',
            'Interview Date',
            'Interview Time',
            'Interview Location',
            'Interview Type',
            'Interviewers',
            'Interview Notes',
            'Interview Result',
            'Interview Feedback',
            'Score',
            'Created At',
        ];
    }
}
