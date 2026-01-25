<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Document')</title>
    <style>
        /*
        |--------------------------------------------------------------------------
        | PDF Base Styles - Shared across all PDF templates
        |--------------------------------------------------------------------------
        | This base layout provides common styles for DomPDF generation.
        | Extend this layout using @extends('pdf.layouts.base')
        |
        | Available sections:
        | - @section('title') - Document title
        | - @section('page-setup') - @page CSS rules
        | - @section('styles') - Additional CSS
        | - @section('content') - Main content
        |--------------------------------------------------------------------------
        */

        /* Page Setup - Override in child templates */
        @yield('page-setup')

        /* ===== CSS Reset & Base Styles ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
        }

        /* ===== Typography ===== */
        h1 { font-size: 16px; font-weight: bold; }
        h2 { font-size: 14px; font-weight: bold; }
        h3 { font-size: 12px; font-weight: bold; }

        p {
            margin-bottom: 8px;
            text-align: justify;
        }

        strong { font-weight: bold; }

        /* ===== Organization Header ===== */
        .org-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .org-header .org-logo {
            max-width: 200px;
            margin-bottom: 10px;
        }

        .org-header .org-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .org-header .org-address {
            font-size: 10px;
            color: #555;
        }

        /* ===== Report Header ===== */
        .report-header {
            text-align: center;
            margin-bottom: 15px;
        }

        .report-header .report-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .report-header .report-subtitle {
            font-size: 11px;
            color: #333;
        }

        .report-header .report-date {
            font-size: 11px;
            margin-top: 5px;
        }

        /* ===== Page Info (Print date, Page numbers) ===== */
        .page-meta {
            position: relative;
            margin-bottom: 20px;
        }

        .page-meta .print-info {
            position: absolute;
            top: 0;
            left: 0;
            font-size: 10px;
        }

        .page-meta .page-number {
            position: absolute;
            top: 0;
            right: 0;
            font-size: 10px;
        }

        /* ===== Tables ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table.bordered {
            border: 1px solid #999;
        }

        table.bordered th,
        table.bordered td {
            border: 1px solid #999;
        }

        table.minimal th {
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
        }

        table.minimal td {
            border: none;
        }

        th {
            padding: 8px 5px;
            font-weight: bold;
            text-align: left;
            background-color: #f5f5f5;
            font-size: 10px;
        }

        td {
            padding: 6px 5px;
            font-size: 10px;
        }

        th.center, td.center {
            text-align: center;
        }

        th.right, td.right {
            text-align: right;
        }

        /* Zebra striping */
        table.striped tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }

        /* ===== Fixed Footer ===== */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }

        .footer .footer-logo {
            max-width: 120px;
            margin-bottom: 5px;
        }

        .footer .footer-text {
            margin: 0;
        }

        /* ===== Utility Classes ===== */
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-justify { text-align: justify; }

        .font-bold { font-weight: bold; }
        .font-italic { font-style: italic; }

        .text-sm { font-size: 9px; }
        .text-base { font-size: 11px; }
        .text-lg { font-size: 13px; }

        .mt-1 { margin-top: 5px; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }

        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }

        .py-1 { padding-top: 5px; padding-bottom: 5px; }
        .py-2 { padding-top: 10px; padding-bottom: 10px; }

        .border-top { border-top: 1px solid #999; }
        .border-bottom { border-bottom: 1px solid #999; }

        /* ===== Signature Section ===== */
        .signature-section {
            margin-top: 30px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            width: 200px;
            margin-top: 40px;
            margin-bottom: 5px;
        }

        .signature-label {
            font-size: 10px;
        }

        /* ===== Additional Styles from Child Templates ===== */
        @yield('styles')
    </style>
</head>
<body>
    @yield('content')

    {{-- Page numbering script - included by default, can be disabled --}}
    @section('page-numbering')
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 9;
            $pageText = "Page {PAGE_NUM} of {PAGE_COUNT}";
            // Position varies by orientation - override in child if needed
            // Default: top-right for landscape (x=750), adjust for portrait (x=500)
            $x = {{ $pageNumberX ?? 750 }};
            $y = {{ $pageNumberY ?? 20 }};
            $pdf->page_text($x, $y, $pageText, $font, $size, array(0, 0, 0));
        }
    </script>
    @show
</body>
</html>
