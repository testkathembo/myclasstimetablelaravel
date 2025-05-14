<form action="{{ route('units.store') }}" method="POST">
    @csrf
    <div>
        <label for="code">Code</label>
        <input type="text" name="code" id="code" required>
    </div>
    <div>
        <label for="name">Name</label>
        <input type="text" name="name" id="name" required>
    </div>
    <div>
        <label for="semester_id">Semester</label>
        <select name="semester_id" id="semester_id">
            <option value="">Select Semester</option>
            @foreach ($semesters as $semester)
                <option value="{{ $semester->id }}">{{ $semester->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit">Create Unit</button>
</form>
