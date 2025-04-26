<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Timetable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Exam Timetable</h1>
    </div>
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Date</th>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Time</th>
                <th>Venue</th>
                <th>Chief Invigilator</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($timetables as $timetable)
                <tr>
                    <td>{{ $timetable->day }}</td>
                    <td>{{ $timetable->date }}</td>
                    <td>{{ $timetable->unit_code }}</td>
                    <td>{{ $timetable->unit_name }}</td>
                    <td>{{ $timetable->start_time }} - {{ $timetable->end_time }}</td>
                    <td>{{ $timetable->venue }}</td>
                    <td>{{ $timetable->chief_invigilator }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
