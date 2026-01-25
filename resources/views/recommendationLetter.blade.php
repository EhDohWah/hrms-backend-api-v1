<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Recommendation Letter</title>
    <style>
        @page {
            margin-top: 0.5in;
            margin-bottom: 0.5in;
            margin-left: 0.79in;
            margin-right: 0.79in;
        }
        body {
            font-family: 'DejaVu Sans', Calibri, sans-serif;
            line-height: 1.6;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #333;
        }
        p {
            text-align: justify;
            margin-bottom: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 15px;
        }
        .header img {
            width: 180px;
        }
        .header h1 {
            margin: 10px 0 5px 0;
            font-size: 18px;
            color: #0066cc;
        }
        .header p {
            margin: 0;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        .date {
            text-align: right;
            margin: 20px 0;
            font-size: 11px;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 30px 0;
            text-decoration: underline;
            color: #0066cc;
        }
        .greeting {
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 15px;
        }
        .employee-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .employee-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .employee-info td {
            padding: 5px 10px;
            vertical-align: top;
        }
        .employee-info td:first-child {
            font-weight: bold;
            width: 35%;
            color: #555;
        }
        .employment-history {
            margin: 20px 0;
        }
        .employment-history h3 {
            font-size: 13px;
            color: #0066cc;
            margin-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        .employment-history table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .employment-history th {
            background-color: #0066cc;
            color: white;
            padding: 8px;
            text-align: left;
        }
        .employment-history td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .employment-history tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .signature {
            margin-top: 40px;
        }
        .signature-line {
            margin-top: 50px;
            border-top: 1px solid #333;
            width: 200px;
            padding-top: 5px;
        }
        .footer {
            font-size: 9px;
            color: #666;
            text-align: center;
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }
        .footer img {
            width: 120px;
            margin-bottom: 5px;
        }
        .highlight {
            font-weight: bold;
            color: #0066cc;
        }
    </style>
</head>
<body>

<div class="header">
    <img src="{{ public_path('images/logo.png') }}" alt="Logo">
    <h1>SHOKLO MALARIA RESEARCH UNIT</h1>
    <p>Faculty of Tropical Medicine, Mahidol University</p>
</div>

<div class="date">Date: {{ $date }}</div>

<div class="title">TO WHOM IT MAY CONCERN</div>

<div class="greeting">
    <strong>RE: Letter of Recommendation for {{ $employee_name }}</strong>
</div>

<div class="content">
    <p>
        This letter is to confirm that <span class="highlight">{{ $employee_name }}</span>
        (Staff ID: <span class="highlight">{{ $staff_id }}</span>) has been employed with
        <span class="highlight">{{ $organization }}</span> from
        <span class="highlight">{{ $start_date }}</span> to
        <span class="highlight">{{ $end_date }}</span>,
        a total period of <span class="highlight">{{ $tenure_text }}</span>.
    </p>

    <div class="employee-info">
        <table>
            <tr>
                <td>Employee Name:</td>
                <td>{{ $employee_name }}</td>
            </tr>
            <tr>
                <td>Staff ID:</td>
                <td>{{ $staff_id }}</td>
            </tr>
            <tr>
                <td>Organization:</td>
                <td>{{ $organization }}</td>
            </tr>
            <tr>
                <td>Last Position Held:</td>
                <td>{{ $current_position }}</td>
            </tr>
            <tr>
                <td>Department:</td>
                <td>{{ $current_department }}</td>
            </tr>
            <tr>
                <td>Employment Period:</td>
                <td>{{ $start_date }} - {{ $end_date }}</td>
            </tr>
            <tr>
                <td>Total Tenure:</td>
                <td>{{ $tenure_text }}</td>
            </tr>
        </table>
    </div>

    @if($employment_history && count($employment_history) > 0)
    <div class="employment-history">
        <h3>Employment History</h3>
        <table>
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Department</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employment_history as $history)
                <tr>
                    <td>{{ $history['position'] }}</td>
                    <td>{{ $history['department'] }}</td>
                    <td>{{ $history['start_date'] }}</td>
                    <td>{{ $history['end_date'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <p>
        During their tenure with us, {{ $employee_name }} demonstrated professionalism,
        dedication, and a strong work ethic. They fulfilled their responsibilities
        diligently and contributed positively to our organization.
    </p>

    <p>
        We wish {{ $employee_name }} all the best in their future endeavors.
        Should you require any further information, please do not hesitate to contact us.
    </p>
</div>

<div class="signature">
    <p>Yours faithfully,</p>

    <div class="signature-line">
        <p style="margin: 0; font-weight: bold;">Suttinee Seechaikham</p>
        <p style="margin: 0; font-size: 11px;">Human Resources Manager</p>
        <p style="margin: 0; font-size: 11px;">Shoklo Malaria Research Unit</p>
    </div>
</div>

<div class="footer">
    <img src="{{ public_path('images/orgs.png') }}" alt="Organization Logo">
    <p>
        BHF/SMRU Office | 78/1 Moo 5, Mae Ramat Sub-District, Mae Ramat District, Tak Province, 63140 | www.shoklo-unit.com<br>
        Phone: +66 55 532 026 | Email: hr@shoklo-unit.com
    </p>
</div>

</body>
</html>
