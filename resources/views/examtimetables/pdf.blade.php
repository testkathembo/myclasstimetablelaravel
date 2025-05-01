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
                <th>Date</th>
                <th>Unit Code</th>
                <th>Unit Name</th>
                <th>Semester</th>
                <th>Venue</th>
                <th>Time</th>
                <th>Chief Invigilator</th>
            </tr>
        </thead>
        <tbody>
            @forelse($examTimetables as $exam)
            <tr>
                <td>{{ $exam->day }}</td>
                <td>{{ $exam->date }}</td>
                <td>{{ $exam->unit_code }}</td>
                <td>{{ $exam->unit_name }}</td>
                <td>{{ $exam->semester_name }}</td>
                <td>{{ $exam->venue }} ({{ $exam->location }})</td>
                <td>{{ $exam->start_time }} - {{ $exam->end_time }}</td>
                <td>{{ $exam->chief_invigilator }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center;">No exam timetables available</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>This is an official document. Please keep it for your records.</p>
    </div>
</body>
</html>