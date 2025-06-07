<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Exam Timetable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #4f46e5;
            margin: 0;
            font-size: 24px;
        }
        .student-info {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .student-info h2 {
            margin: 0 0 10px 0;
            color: #374151;
            font-size: 18px;
        }
        .student-info p {
            margin: 5px 0;
            color: #6b7280;
        }
        .summary {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            text-align: center;
        }
        .summary-item {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            min-width: 150px;
        }
        .summary-item h3 {
            margin: 0;
            font-size: 24px;
            color: #4f46e5;
        }
        .summary-item p {
            margin: 5px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .timetable-table th {
            background-color: #4f46e5;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
        }
        .timetable-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .timetable-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .unit-code {
            font-weight: bold;
            color: #4f46e5;
        }
        .unit-name {
            color: #6b7280;
            font-size: 10px;
        }
        .venue {
            background-color: #dcfce7;
            color: #166534;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .time-slot {
            font-family: 'Courier New', monospace;
            background-color: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }
        .status-completed {
            background-color: #f3f4f6;
            color: #6b7280;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-upcoming {
            background-color: #dbeafe;
            color: #1d4ed8;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-today {
            background-color: #fef2f2;
            color: #dc2626;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Exam Timetable</h1>
        <p>Academic Examination Schedule</p>
    </div>

    <div class="student-info">
        <h2>Student Information</h2>
        <p><strong>Name:</strong> {{ $student->first_name }} {{ $student->last_name }}</p>
        <p><strong>Student ID:</strong> {{ $student->student_id ?? $student->code ?? 'N/A' }}</p>
        <p><strong>Email:</strong> {{ $student->email }}</p>
        <p><strong>Generated:</strong> {{ $generatedAt }}</p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <h3>{{ $examTimetables->count() }}</h3>
            <p>Total Exams</p>
        </div>
        <div class="summary-item">
            <h3>{{ $examTimetables->where('exam_date', '>=', now()->format('Y-m-d'))->count() }}</h3>
            <p>Upcoming Exams</p>
        </div>
        <div class="summary-item">
            <h3>{{ $examTimetables->unique('unit_id')->count() }}</h3>
            <p>Units</p>
        </div>
    </div>

    @if($examTimetables->count() > 0)
        <table class="timetable-table">
            <thead>
                <tr>
                    <th>Date & Day</th>
                    <th>Unit</th>
                    <th>Time</th>
                    <th>Venue</th>
                    <th>Invigilator</th>
                    <th>Students</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($examTimetables as $exam)
                    @php
                        $examDate = \Carbon\Carbon::parse($exam->exam_date);
                        $today = now();
                        $status = $examDate->isFuture() ? 'upcoming' : ($examDate->isToday() ? 'today' : 'completed');
                    @endphp
                    <tr>
                        <td>
                            <div style="font-weight: bold;">{{ $examDate->format('M d, Y') }}</div>
                            <div class="unit-name">{{ $exam->day }}</div>
                        </td>
                        <td>
                            <div class="unit-code">{{ $exam->unit_code }}</div>
                            <div class="unit-name">{{ $exam->unit_name }}</div>
                        </td>
                        <td>
                            <div class="time-slot">{{ $exam->start_time }} - {{ $exam->end_time }}</div>
                        </td>
                        <td>
                            <div class="venue">{{ $exam->venue }}</div>
                            @if($exam->location)
                                <div class="unit-name">{{ $exam->location }}</div>
                            @endif
                        </td>
                        <td>{{ $exam->chief_invigilator }}</td>
                        <td style="text-align: center; font-weight: bold;">{{ $exam->no }}</td>
                        <td>
                            <span class="status-{{ $status }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <h3>No Exam Timetables Found</h3>
            <p>You don't have any scheduled examinations at this time.</p>
        </div>
    @endif

    <div class="footer">
        <p>This document was automatically generated on {{ $generatedAt }}</p>
        <p>For any discrepancies, please contact the examination office immediately.</p>
        <p>© {{ date('Y') }} Timetabling System Management - All rights reserved</p>
    </div>
</body>
</html>