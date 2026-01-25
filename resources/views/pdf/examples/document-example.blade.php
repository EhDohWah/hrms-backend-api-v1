{{--
|--------------------------------------------------------------------------
| Example: How to use the Document Layout
|--------------------------------------------------------------------------
| This is a reference template showing how to create a formal document
| PDF (letters, certificates) using the shared document layout.
|
| Copy this file and customize for your specific document.
|--------------------------------------------------------------------------
--}}

@extends('pdf.layouts.document')

@section('title', 'Sample Document')

{{-- Optional: Custom header/letterhead --}}
{{-- Uncomment to override default letterhead
@section('document-header')
<div class="letterhead">
    <img src="{{ public_path('images/custom-logo.png') }}" alt="Logo" class="logo">
    <div class="org-name">Custom Organization Name</div>
</div>
@endsection
--}}

@section('document-content')
{{-- Date --}}
<div class="document-date">
    {!! $date !!}
</div>

{{-- Subject line --}}
<div class="document-subject">
    Subject: {{ $subject }}
</div>

{{-- Greeting/Salutation --}}
<div class="document-greeting">
    Dear <strong>{{ $recipientName }}</strong>,
</div>

{{-- Document body --}}
<div class="document-body">
    <p>
        We are pleased to inform you that your application has been reviewed
        and we would like to offer you the position of <strong>{{ $position }}</strong>.
    </p>

    <p>
        The compensation package includes a monthly salary of
        <strong>THB {{ number_format($salary, 2) }}</strong> with the following benefits:
    </p>

    <ul style="margin-left: 20px; margin-bottom: 15px;">
        <li>Health insurance coverage</li>
        <li>Annual leave: 26 days per year</li>
        <li>Provident fund after probation</li>
    </ul>

    <p>
        Please confirm your acceptance by signing and returning this letter
        by <strong>{!! $deadline !!}</strong>.
    </p>

    <p>
        We look forward to welcoming you to our team.
    </p>
</div>

{{-- Signature block --}}
<div class="signature-block">
    <p class="closing">Sincerely,</p>
    <p class="signatory-name">{{ $signatoryName ?? 'HR Manager' }}</p>
    <p class="signatory-title">{{ $signatoryTitle ?? 'Human Resources' }}</p>
    <p class="signatory-org">Shoklo Malaria Research Unit</p>
</div>

{{-- Acceptance section (optional, for offer letters) --}}
<div class="acceptance-section">
    <p>
        I, <strong>{{ $recipientName }}</strong>, accept the offer and confirm
        my starting date is <span class="form-line"></span>.
    </p>
    <br>
    <p>Signature: <span class="form-line" style="min-width: 250px;"></span></p>
    <p>Date: <span class="form-line" style="min-width: 150px;"></span></p>
</div>
@endsection

{{-- Optional: Custom footer --}}
{{-- Uncomment to override default footer
@section('document-footer')
<div class="document-footer">
    <p>Custom footer content here</p>
</div>
@endsection
--}}

{{-- Optional: Add document-specific styles --}}
@section('document-styles')
/* Custom styles for this specific document */
ul {
    list-style-type: disc;
}
li {
    margin-bottom: 5px;
}
@endsection
