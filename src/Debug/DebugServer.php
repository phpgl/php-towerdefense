<?php

namespace TowerDefense\Debug;

use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;

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
            if ($input = socket_read($socketItem, 16384)) {
                $input = trim($input);

                echo "client $k: $input\n";

                $response = $this->handleInput($input, $entities);
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

    /**
     * Handle the input command from the client
     * 
     * @param string $input 
     * @param EntitiesInterface $entities 
     * @return string 
     */
    private function handleInput(string $input, EntitiesInterface $entities): string
    {
        $response = '';

        switch ($input) {
            case 'quit':
                $response = 'Bye!' . "\n";
                break;
            case 'help':
                $response = 'Available commands:' . "\n";
                $response .= 'quit' . "\n";
                $response .= 'help' . "\n";
                $response .= 'list_transform_components' . "\n";
                break;
            case 'list_transform_components':
                $response = 'Entities:' . "\n";
                foreach ($entities->view(Transform::class) as $entity => $transform) {
                    $response .= "Entity " . $entity . " Position: " . $transform->position->__toString() . "\n";
                }
                break;
            default:
                $response = 'Unknown command' . "\n";
                break;
        }

        return $response;
    }
}
