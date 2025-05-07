@component('mail::message')
# {{ $subject }}

{{ $greeting }}

{{ $message }}

@component('mail::panel')
## Exam Details

**Unit:** {{ $exam_details['unit'] }}  
**Date:** {{ $exam_details['date'] }} ({{ $exam_details['day'] }})  
**Time:** {{ $exam_details['time'] }}  
**Venue:** {{ $exam_details['venue'] }}
@endcomponent

@if(!empty($changes))
## Changes Made:

@foreach($changes as $field => $values)
- **{{ ucfirst($field) }}**: Changed from "{{ $values['old'] }}" to "{{ $values['new'] }}"
@endforeach
@endif

{{ $closing }}

@component('mail::button', ['url' => $url])
View Updated Timetable
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
