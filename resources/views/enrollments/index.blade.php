<table>
    <thead>
        <tr>
            <th>Student Code</th>
            <th>Unit</th>
            <th>Semester</th>
            <th>Group</th> <!-- Add group column -->
        </tr>
    </thead>
    <tbody>
        @foreach ($enrollments as $enrollment)
            <tr>
                <td>{{ $enrollment->student_code }}</td>
                <td>{{ $enrollment->unit->name }}</td>
                <td>{{ $enrollment->semester->name }}</td>
                <td>{{ $enrollment->group }}</td> <!-- Display group -->
            </tr>
        @endforeach
    </tbody>
</table>
