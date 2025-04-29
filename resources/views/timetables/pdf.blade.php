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
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <h1>Exam Timetable</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Day</th>
                <th>Date</th>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Semester</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Venue</th>
                <th>Chief Invigilator</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($timetables as $timetable)
                <tr>
                    <td>{{ $timetable['id'] }}</td>
                    <td>{{ $timetable['day'] }}</td>
                    <td>{{ $timetable['date'] }}</td>
                    <td>{{ $timetable['unit_code'] }}</td>
                    <td>{{ $timetable['unit_name'] }}</td>
                    <td>{{ $timetable['semester_name'] }}</td>
                    <td>{{ $timetable['start_time'] }}</td>
                    <td>{{ $timetable['end_time'] }}</td>
                    <td>{{ $timetable['venue'] }}</td>
                    <td>{{ $timetable['chief_invigilator'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
