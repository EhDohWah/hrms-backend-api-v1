@extends('pdf.layouts.base')

{{--
|--------------------------------------------------------------------------
| Document Layout - For letters and certificates (A4 Portrait)
|--------------------------------------------------------------------------
| Use this layout for formal documents like:
| - Job Offer Letters
| - Employment Certificates
| - Internet Certificates
| - Formal Letters
|
| Usage:
| @extends('pdf.layouts.document')
| @section('title', 'Document Title')
| @section('document-content') ... your content ... @endsection
| @section('document-footer') ... optional custom footer ... @endsection
|--------------------------------------------------------------------------
--}}

@section('page-setup')
@page {
    size: A4 portrait;
    margin-top: 0.5in;
    margin-bottom: 0.5in;
    margin-left: 0.75in;
    margin-right: 0.75in;
}
@endsection

@section('styles')
/* Document-specific styles */
body {
    font-family: 'DejaVu Sans', Calibri, Arial, sans-serif;
    font-size: 12px;
    line-height: 1.6;
}

/* Letterhead */
.letterhead {
    text-align: center;
    margin-bottom: 20px;
}

.letterhead .logo {
    max-width: 180px;
    margin-bottom: 10px;
}

.letterhead .org-name {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 3px;
}

.letterhead .org-tagline {
    font-size: 10px;
    color: #555;
}

/* Document date */
.document-date {
    text-align: right;
    margin: 20px 0 30px 0;
    font-size: 12px;
}

/* Subject line */
.document-subject {
    font-weight: bold;
    margin-bottom: 15px;
    font-size: 12px;
}

/* Salutation/Greeting */
.document-greeting {
    margin-bottom: 15px;
}

/* Document body content */
.document-body {
    margin-bottom: 20px;
}

.document-body p {
    margin-bottom: 12px;
    text-align: justify;
}

/* Signature block */
.signature-block {
    margin-top: 30px;
}

.signature-block .closing {
    margin-bottom: 50px;
}

.signature-block .signatory-name {
    font-weight: bold;
}

.signature-block .signatory-title {
    font-size: 11px;
}

.signature-block .signatory-org {
    font-size: 11px;
}

/* Acceptance section (for offer letters) */
.acceptance-section {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px dashed #999;
}

.acceptance-section .acceptance-title {
    font-weight: bold;
    margin-bottom: 15px;
}

.acceptance-section .form-line {
    border-bottom: 1px solid #000;
    display: inline-block;
    min-width: 200px;
    margin: 0 5px;
}

/* Document footer */
.document-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 9px;
    color: #555;
    padding-top: 10px;
}

.document-footer .footer-logo {
    max-width: 120px;
    margin-bottom: 5px;
}

.document-footer .footer-address {
    margin: 5px 0;
}

.document-footer .footer-contact {
    margin: 0;
}

/* Highlighted text */
.highlight {
    font-weight: bold;
}

/* Superscript for date ordinals */
sup {
    font-size: 8px;
    vertical-align: super;
}

@yield('document-styles')
@endsection

@section('content')
<div class="document-container">
    {{-- Letterhead/Header --}}
    @hasSection('document-header')
        @yield('document-header')
    @else
        <div class="letterhead">
            @if(file_exists(public_path('images/logo.png')))
                <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo">
            @endif
        </div>
    @endif

    {{-- Main Document Content --}}
    @yield('document-content')

    {{-- Footer --}}
    @hasSection('document-footer')
        @yield('document-footer')
    @else
        <div class="document-footer">
            @if(file_exists(public_path('images/orgs.png')))
                <img src="{{ public_path('images/orgs.png') }}" alt="Organization Logo" class="footer-logo">
            @endif
            <p class="footer-address">
                BHF/SMRU Office | 78/1 Moo 5, Mae Ramat Sub-District, Mae Ramat District, Tak Province, 63140 | www.shoklo-unit.com
            </p>
            <p class="footer-contact">
                Phone: +66 55 532 026
            </p>
        </div>
    @endif
</div>
@endsection

{{-- Disable page numbering by default for formal documents --}}
@section('page-numbering')
{{-- Override in child template if page numbers are needed --}}
@endsection
