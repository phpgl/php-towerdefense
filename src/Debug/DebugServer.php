<?php

namespace TowerDefense\Debug;

class DebugServer
{
    private string $address = '127.0.0.1';
    private int $port = 8145;
    private object $socket;
    private array $clients = [];

    public function __construct()
    {
    }

    public function start(): void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);

        socket_bind($this->socket, $this->address, $this->port) or die('Could not bind to address');

        socket_listen($this->socket);

        socket_set_nonblock($this->socket);
    }

    public function stop(): void
    {
        foreach ($this->clients as $i => $client) {
            socket_close($client);
            unset($this->clients[$i]);
        }

        socket_close($this->socket);
    }

    public function process(): void
    {
        if ($newsock = socket_accept($this->socket)) {
            echo "Get new socket\n";
            socket_set_nonblock($newsock);
            echo "New client connected\n";
            $this->clients[] = $newsock;
        }

        if (count($this->clients) < 1) {
            return;
        }

        $read = $this->clients;

        $changed = socket_select($read, $w, $e, 0);

        if (false == $changed) {
            return;
        }

        if ($changed < 1) {
            return;
        }

        foreach ($read as $k => $socketItem) {
            if ($input = socket_read($socketItem, 1024)) {
                $input = trim($input);

                echo "client $k: $input\n";

                $response = "Received" . "\n";
                socket_write($socketItem, $response, strlen($response));

                if ($input == 'quit') {
                    socket_close($socketItem);
                    unset($this->clients[$k]);
                }
            } else {
                echo "Socket read error: " . socket_strerror(socket_last_error()) . "\n";
            }
        }
    }
}
