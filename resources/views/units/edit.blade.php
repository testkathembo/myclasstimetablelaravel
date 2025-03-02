@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Edit Unit</h1>
        <form action="{{ route('units.update', $unit->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="code">Code</label>
                <input type="text" name="code" class="form-control" value="{{ $unit->code }}" required>
            </div>
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" name="name" class="form-control" value="{{ $unit->name }}" required>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
