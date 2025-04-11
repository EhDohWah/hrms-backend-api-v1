<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Job Offer</title>
    <style>
        @page {
            margin-top: 0.49in;
            margin-bottom: 0.19in;
            margin-left: 0.79in;
            margin-right: 0.79in;
        }
        body {
            font-family: Calibri, sans-serif;
            line-height: 1.5;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        p {
            text-align: justify;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header img {
            width: 200px;
        }
        .date {
            text-align: right;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .subject, .greeting, .content, {
            margin-bottom: 10px;
        }

        .signature {
            margin-top: 5px;
        }

        .footer {
            font-size: 10px;
            color: #555;
            text-align: center;
            margin-top: 10px;
            padding-top: 10px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }

        .footer img {
            width: 150px;
            align-items: center;
        }

        .footer p {
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="header">
    <img src="{{ public_path('images/logo.png') }}" alt="Logo">
</div>

<div class="date">{!! $date !!}</div>

<div class="subject">Subject: {{ $subject }}</div>

<div class="greeting">Dear <strong>{{ $employee_name }}</strong>,</div>

<div class="content">
    <p>
        The Shoklo Malaria Research Unit (SMRU) is delighted to offer you the position of <strong>{{ $position }}</strong>.
        The monthly basic salary will be <strong>{{ $probation_salary }}</strong> during the probation period and
        <strong>{{ $post_probation_salary }}</strong> after passing the probation period.
        There is a probationary period of <strong>3 months</strong> from the commencement date.
    </p>

    <p>
        The employee will be expected to work an average of 8 hours a day, for
        6 days a week. Annual vacation days will be given
        2 days per month after probation until the end of the calendar year.
        By 1st January, staff who have already passed probation will receive
        26 days / year of annual vacation automatically.
    </p>

    <p>
        Provident fund will be entitled after a successful completion of probation period.
    </p>

    <p>
        This job offer is contingent upon completion of a satisfactory background check. We reserve the right to end our employment agreement with you should the results of your background investigation not be successful.
    </p>

    <p>
        Please confirm your acceptance of this offer by signing and returning this letter by <strong>{!! $acceptance_deadline !!}</strong> and confirm to us your earliest starting date.
    </p>

    <p>
        We are excited to have you join our team! If you have any questions, please feel free to reach out at any time.
    </p>
</div>

<div class="signature">
    <p>Sincerely,</p>
    <br>
    <p>
        Suttinee Seechaikham<br>
        HR Manager<br>
        Shoklo Malaria Research Unit
    </p>
</div>

<div class="acceptance">
    <p>
        I, <strong>{{ $employee_name }}</strong>, accept the job offer and confirm my starting date is __________________.
    </p>
    <br>
    <p>
        Signature: ______________________________
    </p>
    <p>
    Date: __________________________________
    </p>
</div>

<div class="footer">
    <img src="{{ public_path('images/orgs.png') }}" alt="BHF Logo">
    <p>
        BHF/SMRU Office | 78/1 Moo 5, Mae Ramat Sub-District, Mae Ramat District, Tak Province, 63140 | www.shoklo-unit.com<br>
        Phone: +66 55 532 026
    </p>
</div>

</body>
</html>
