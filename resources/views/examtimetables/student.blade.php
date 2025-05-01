<!DOCTYPE html>
<html>
<head>
    <title>Exam Timetable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        h1, h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Exam Timetable</h1>
    <h2>{{ $currentSemester->name }}</h2>
    <p>Student: {{ $student->first_name }} {{ $student->last_name }} ({{ $student->code }})</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Time</th>
                <th>Venue</th>
                <th>Location</th>
                <th>Unit</th>
                <th>Chief Invigilator</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($examTimetables as $exam)
                <tr>
                    <td>{{ $exam->date }}</td>
                    <td>{{ $exam->day }}</td>
                    <td>{{ $exam->start_time }} - {{ $exam->end_time }}</td>
                    <td>{{ $exam->venue }}</td>
                    <td>{{ $exam->location }}</td>
                    <td>{{ $exam->unit->code }} - {{ $exam->unit->name }}</td>
                    <td>{{ $exam->chief_invigilator }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
