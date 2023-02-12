<?php

namespace TowerDefense\Debug;

use VISU\ECS\EntitiesInterface;

/**
 * Class DebugServer
 * 
 * @package TowerDefense\Debug
 */
class DebugServer
{
    private string $address = '127.0.0.1'; // localhost
    private int $port = 8145; // default port
    private object $socket; // the server socket
    private array $clients = []; // the client sockets

    /**
     * DebugServer constructor.
     * 
     * @param string $address 
     * @param int $port 
     * @return void 
     */
    public function __construct(string $address, int $port)
    {
        $this->address = $address;
        $this->port = $port;
    }

    /**
     * Start the debug server
     * @return void 
     */
    public function start(): void
    {
        // create the server socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);

        // bind the socket to the address and port
        socket_bind($this->socket, $this->address, $this->port) or die('Could not bind to address');

        // start listening for connections
        socket_listen($this->socket);

        // set the socket to non-blocking
        socket_set_nonblock($this->socket);
    }

    /**
     * Stop the debug server
     * 
     * @return void 
     */
    public function stop(): void
    {
        // close all clients
        foreach ($this->clients as $i => $client) {
            socket_close($client);
            unset($this->clients[$i]);
        }

        // close the server socket
        socket_close($this->socket);
    }

    /**
     * Process the debug server input and output
     * 
     * @return void 
     */
    public function process(EntitiesInterface $entities): void
    {
        // check for new connections
        if ($newsock = socket_accept($this->socket)) {
            echo "Get new socket\n";
            socket_set_nonblock($newsock);
            echo "New client connected\n";
            $this->clients[] = $newsock;
        }

        // check for existing clients
        if (count($this->clients) < 1) {
            return;
        }

        $read = $this->clients;

        // check for input
        $changed = socket_select($read, $w, $e, 0);

        if (false == $changed) {
            return;
        }

        if ($changed < 1) {
            return;
        }

        // process input
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
                // most probably the socket was closed from the client side
                echo "Socket read error: " . socket_strerror(socket_last_error()) . "\n";
                socket_close($socketItem);
                unset($this->clients[$k]);
            }
        }
    }
}
