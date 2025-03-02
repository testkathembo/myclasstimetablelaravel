@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Units</h1>
        <a href="{{ route('units.create') }}" class="btn btn-primary">Add Unit</a>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    <tr>
                        <td>{{ $unit->id }}</td>
                        <td>{{ $unit->code }}</td>
                        <td>{{ $unit->name }}</td>
                        <td>
                            <a href="{{ route('units.show', $unit->id) }}" class="btn btn-info">View</a>
                            <a href="{{ route('units.edit', $unit->id) }}" class="btn btn-warning">Edit</a>
                            <form action="{{ route('units.destroy', $unit->id) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
