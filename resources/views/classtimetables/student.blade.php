<?php
<!DOCTYPE html>
<html>
<head>
    <title>My Class Timetable</title>
    <style>
        body { 
            font-family: DejaVu Sans, sans-serif; 
            margin: 20px;
            font-size: 12px;
            background: #f6f8fa;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #003366;
            padding-bottom: 15px;
            background: #fff;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 8px #e0e0e0;
        }
        .logo {
            width: 90px;
            margin-bottom: 10px;
        }
        .university-name {
            color: #003366;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }
        .university-sub {
            color: #c8102e;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .student-info {
            margin-bottom: 20px;
            background: #e9ecef;
            padding: 12px 18px;
            border-radius: 6px;
            border-left: 5px solid #003366;
            font-size: 13px;
        }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 10px;
            color: #003366;
            letter-spacing: 0.5px;
        }
        .timetable-table, .enrollment-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 4px #e0e0e0;
        }
        .timetable-table th, .timetable-table td, .enrollment-table th, .enrollment-table td { 
            border: 1px solid #b0b0b0; 
            padding: 8px 6px; 
            text-align: left; 
            font-size: 11px;
        }
        .timetable-table th, .enrollment-table th { 
            background: #003366; 
            color: #fff;
            font-weight: bold;
        }
        .day-group {
            background-color: #f6f8fa;
            font-weight: bold;
            color: #003366;
        }
        .online-class {
            background-color: #e3f2fd;
        }
        .physical-class {
            background-color: #fff8e1;
        }
        .footer {
            margin-top: 35px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 2px solid #003366;
            padding-top: 12px;
            background: #fff;
            border-radius: 0 0 8px 8px;
        }
        .watermark {
            position: fixed;
            top: 40%;
            left: 50%;
            opacity: 0.07;
            font-size: 90px;
            color: #003366;
            transform: translate(-50%, -50%) rotate(-20deg);
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/strathmore-logo.png') }}" alt="Strathmore Logo" class="logo">
        <div class="university-name">STRATHMORE UNIVERSITY</div>
        <div class="university-sub">Timetabling System</div>
        <h2 style="color:#c8102e; margin-bottom: 0;">MY CLASS TIMETABLE</h2>
        <div style="font-size:13px; color:#003366;">{{ $currentSemester->name ?? 'Current Semester' }}</div>
    </div>

    <div class="student-info">
        <strong>Student:</strong> {{ $student->first_name }} {{ $student->last_name }} ({{ $student->code }})<br>
        <strong>Generated:</strong> {{ $generatedAt }}<br>
        <strong>Total Units:</strong> {{ $enrollments->count() }}
    </div>

    @if($classTimetables->count() > 0)
        <div class="section-title">📅 CLASS SCHEDULE</div>
        <table class="timetable-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Unit</th>
                    <th>Venue</th>
                    <th>Mode</th>
                    <th>Lecturer</th>
                    <th>Group</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    $groupedTimetables = $classTimetables->groupBy('day');
                @endphp
                @foreach($days as $day)
                    @if(isset($groupedTimetables[$day]))
                        @foreach($groupedTimetables[$day] as $index => $timetable)
                            <tr class="{{ strtolower($timetable->teaching_mode ?? 'physical') }}-class">
                                @if($index === 0)
                                    <td rowspan="{{ count($groupedTimetables[$day]) }}" class="day-group">
                                        {{ $day }}
                                    </td>
                                @endif
                                <td>{{ $timetable->start_time }} - {{ $timetable->end_time }}</td>
                                <td>
                                    <strong>{{ $timetable->unit_code }}</strong><br>
                                    <small>{{ $timetable->unit_name }}</small>
                                </td>
                                <td>
                                    {{ $timetable->venue ?? 'TBA' }}<br>
                                    <small>{{ $timetable->location ?? '' }}</small>
                                </td>
                                <td>
                                    @if(strtolower($timetable->teaching_mode ?? '') === 'online')
                                        🌐 Online
                                    @else
                                        🏢 Physical
                                    @endif
                                </td>
                                <td>{{ $timetable->lecturer ?? 'TBA' }}</td>
                                <td>{{ $timetable->group_name ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @else
        <div class="section-title" style="color:#c8102e;">⚠️ NO CLASS SCHEDULE FOUND</div>
        <p>No timetable entries have been created for your enrolled units yet. Please contact the administration.</p>
    @endif

    <div class="section-title">📚 ENROLLED UNITS SUMMARY</div>
    <table class="enrollment-table">
        <thead>
            <tr>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Group</th>
                <th>Semester</th>
            </tr>
        </thead>
        <tbody>
            @foreach($enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->unit->code ?? 'N/A' }}</td>
                    <td>{{ $enrollment->unit->name ?? 'N/A' }}</td>
                    <td>{{ $enrollment->group->name ?? 'N/A' }}</td>
                    <td>{{ $enrollment->semester->name ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This is an official timetable document generated from the Strathmore Timetabling System.</p>
        <p>For any discrepancies or updates, please contact the Academic Office.</p>
        <p style="color:#c8102e; font-weight:bold;">Strathmore University &copy; {{ date('Y') }}</p>
    </div>
    <div class="watermark">Strathmore</div>
</body>
</html>