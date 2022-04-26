<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>__APP_NAME__</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Le styles -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <style>
        body {
            padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
        }
    </style>
    <link href="bootstrap/css/bootstrap-responsive.css" rel="stylesheet" type="text/css">
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
    <a class="navbar-brand" href="#">__APP_NAME__ Admin [__SERVER_NAME__]</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault"
            aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarsExampleDefault">
        <ul class="navbar-nav mr-auto">
            <li class="active nav-item"><a class="nav-link" href="/">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="/about">About</a></li>
            <li class="nav-item"><a class="nav-link" href="/contact">Contact</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <h1 class="text-center mt-4 mb-4"><img alt="server icon" src="images/server.png" width="50px"> EXPSS [__SERVER_NAME__] Statistic</h1>
    <hr>
    <div class="row">
        <div class="col-md-6">
            <h3 class="">Socket Server management</h3>
            <div class="control_server border border-secondary p-2 mb-3 bg-light">
                <div class="server_name">
                    <p class="small">Server name: __SERVER_NAME__</p>
                </div>
                __CONTROL_SERVER_BUTTON__
                <hr>
                <h4>Clear server data</h4>
                <a class="btn btn-danger" href="/socket/clear_data">Clear all data</a>
                <p class="text-danger small">Warning! This operation will delete all information about socket server connections, events and all socket clients.</p>

                <hr>
                <h4>Add new socket client to [__SERVER_NAME__]</h4>
                <a class="btn btn-warning" href="/socket/add_new_client">Add socket client</a>

                __HAS_NEW_CLIENT__

            </div>

            <h3 class="">Web Server metrics</h3>
            <div class="control_metrics border border-secondary p-2 mb-3 bg-light">
                __WS_INFO__
            </div>

            <h3 class="">Socket Server metrics</h3>
            <div class="control_metrics border border-secondary p-2 mb-3 bg-light">

                __SS_INFO__

                <div class="server_max_connections "><p class="small">
                        deleting connections statistic after days: <b>__SERVER_MAX_DAY_CONNECTIONS__</b>
                    </p>
                </div>
                <div class="server_max_events"><p class="small">
                        deleting events statistic after days: <b>__SERVER_MAX_DAY_EVENTS__</b>
                    </p>
                </div>
            </div>

            <h3>Socket clients</h3>
            <div class="socket_clients border border-secondary p-2 mb-3 bg-light">
                __SERVER_SOCKET_CLIENTS__
            </div>

            <h3>Custom events</h3>
            <div class="custom_events border border-secondary p-2 mb-3 bg-light">
                __SERVER_SOCKET_EVENTS__
            </div>
        </div>
        <div class="col-md-6">
            <h3>Socket connections</h3>
            <div class="connections">__CONNECTIONS__</div>
            <p>__CONN_COUNT_VIEW__ socket connections saved in storage</p>
        </div>

    </div>

</div>

<div id="footer">
    <div class="container">
        <p>This page was generated: __GEN_TIME__
        <p class="muted credit">__APP_NAME__ <a href="__DEVELOPER_ORIGIN_LINK__">__DEVELOPER_NAME__</a>.</p>
    </div>
</div>

<script
        src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
        crossorigin="anonymous"></script>
<script src="bootstrap/js/bootstrap.min.js"></script>

</body>
</html>