<?php

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_connect($sock, '127.0.0.1', 8145) === false) {
    echo "socket_connect() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

$run = true;
$msgCounter = 0;
do {
    echo "Command: ";

    $stdin = fopen('php://stdin', 'r');

    $msg = trim(fgets($stdin));
    $msgJSON = json_encode([
        'command' => $msg,
        'request_id' => $msgCounter++
    ]);

    echo "\nRequest:\n$msgJSON\n\n";

    socket_write($sock, $msgJSON, strlen($msgJSON));

    if (false === ($buf = socket_read($sock, 16384))) {
        echo "socket_read() failed, reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    }

    echo "\nResponse:\n";
    print_r(json_decode($buf, true));
    echo "\n";

    if ($msg == 'disconnect') {
        $run = false;
    }
} while ($run);
