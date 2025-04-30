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
            @foreach ($examtimetables as $examtimetable)
                <tr>
                    <td>{{ $examtimetable['id'] }}</td>
                    <td>{{ $examtimetable['day'] }}</td>
                    <td>{{ $examtimetable['date'] }}</td>
                    <td>{{ $examtimetable['unit_code'] }}</td>
                    <td>{{ $examtimetable['unit_name'] }}</td>
                    <td>{{ $examtimetable['semester_name'] }}</td>
                    <td>{{ $examtimetable['start_time'] }}</td>
                    <td>{{ $examtimetable['end_time'] }}</td>
                    <td>{{ $examtimetable['venue'] }}</td>
                    <td>{{ $examtimetable['chief_invigilator'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
