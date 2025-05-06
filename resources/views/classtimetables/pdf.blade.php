<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
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
    <h1>{{ $title }}</h1>
    <div class="header-info">
        <p>Generated at: {{ $generatedAt }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Semester</th>
                <th>Venue</th>
                <th>Time</th>
                <th>Number of Students</th>
                <th>Mode of Teaching</th> <!-- Added column -->
                <th>Lecturer</th>
            </tr>
        </thead>
        <tbody>
            @forelse($classTimetables as $class)
            <tr>
                <td>{{ $class->day }}</td>
                <td>{{ $class->unit_code }}</td>
                <td>{{ $class->unit_name }}</td>
                <td>{{ $class->semester_name }}</td>
                <td>{{ $class->venue }} ({{ $class->location }})</td>
                <td>{{ $class->start_time }} - {{ $class->end_time }}</td>
                <td>{{ $class->no }}</td>
                <td>{{ $class->mode_of_teaching ?? 'N/A' }}</td> <!-- Display mode of teaching -->
                <td>{{ $class->lecturer }}</td>
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
