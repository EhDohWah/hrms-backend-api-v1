<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>HRMS</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
             
        <div class="flex-center position-ref full-height"> 
            <div class="content">

            <div class="mx-auto mb-5 text-center">
                <!-- Custom Logo Box -->
                <div class="d-inline-block position-relative" style="width: 220px; height: 90px; border: 6px solid #0D3E5F; border-radius: 20px; background-color: #fff;">
                    <h1 style="color: #0D3E5F; margin: 0; font-size: 4rem;">HRMS</h1>

                    <!-- SMRU / BHF on the bottom-right corner -->
                        <span style="
                            position: absolute;
                            bottom: -10px;
                            right: 10px;
                            background-color: white;
                            padding: 0 8px;
                            font-weight: 600;
                            color: #0D3E5F;
                            font-size: 0.9rem;
                            ">
                            SMRU / BHF
                        </span>
                </div>
            </div>  
                <div class="title m-b-md">
                    HRMS Backend API
                </div>

                <div class="links">
                    <a href="{{ url('/api/documentation') }}">API Documentation</a>
                    <a href="https://github.com/EhDohWah/hrms-backend-api-v1.git">GitHub</a>
                </div>
            </div>
        </div>
    </body>
</html>
