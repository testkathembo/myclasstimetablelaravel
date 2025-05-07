<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Student Class Timetable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .student-info {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Student Class Timetable</h1>
    <div class="header-info">
        <p>Generated at: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    
    <div class="student-info">
        <p><strong>Student Name:</strong> {{ $student->first_name }} {{ $student->last_name }}</p>
        <p><strong>Student ID:</strong> {{ $student->code }}</p>
        <p><strong>Semester:</strong> {{ $currentSemester->name }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Semester</th>
                <th>Classroom</th>
                <th>Time</th>
                <th>Location</th>
                <th>Lecturer</th>
                <th>Mode of Teaching</th>
            </tr>
        </thead>
        <tbody>
            @forelse($classTimetables as $class)
            <tr>
                <td>{{ $class->day }}</td>
                <td>{{ $class->unit_code }}</td>
                <td>{{ $class->unit_name }}</td>
                <td>{{ $class->semester_name }}</td>
                <td>{{ $class->venue }}</td>
                <td>{{ $class->start_time }} - {{ $class->end_time }}</td>
                <td>{{ $class->location }}</td>
                <td>{{ $class->lecturer }}</td>
                <td>{{ $class->mode_of_teaching ?? 'N/A' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align: center;">No class timetables available</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>This is an official document. Please keep it for your records.</p>
    </div>
</body>
</html>
