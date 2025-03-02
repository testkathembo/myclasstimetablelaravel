@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Unit Details</h1>
        <div class="form-group">
            <label for="code">Code</label>
            <input type="text" name="code" class="form-control" value="{{ $unit->code }}" readonly>
        </div>
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" class="form-control" value="{{ $unit->name }}" readonly>
        </div>
        <a href="{{ route('units.index') }}" class="btn btn-secondary">Back</a>
    </div>
@endsection
