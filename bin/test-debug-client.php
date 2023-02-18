<?php

// create the client socket
if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

// connect to the server
if (socket_connect($sock, '127.0.0.1', 8145) === false) {
    echo "socket_connect() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

$run = true;
$msgCounter = 0;
// client loop
do {
    echo "Command: ";

    // read the command from the console
    $stdin = fopen('php://stdin', 'r');

    // prepare the json message
    $msg = trim(fgets($stdin));
    $request = [
        'command' => $msg,
        'request_id' => $msgCounter++
    ];
    if (stripos($msg, 'console_command') === 0) {
        $request['command'] = 'console_command';
        $request['console_command'] = trim(substr($msg, 16));
    }
    $msgJSON = json_encode($request);

    echo "\nRequest:\n$msgJSON\n\n";

    // send the message to the server
    socket_write($sock, $msgJSON, strlen($msgJSON));

    // error reading the response
    if (false === ($buf = socket_read($sock, 16384))) {
        echo "socket_read() failed, reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    }

    echo "\nResponse:\n";
    print_r(json_decode($buf, true));
    echo "\n";

    // check for disconnect
    if ($msg == 'disconnect') {
        $run = false;
    }
} while ($run);
