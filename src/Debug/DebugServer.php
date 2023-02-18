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
    private string $address = "127.0.0.1"; // localhost
    private int $port = 8145; // default port
    private ?object $socket = null; // the server socket
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
        if (!socket_bind($this->socket, $this->address, $this->port)) {
            // close the server socket
            socket_close($this->socket);
            $this->socket = null;
            echo "DebugServer: Could not bind to address " . $this->address . " on port " . $this->port;
            return;
        }

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
        if ($this->socket !== null) {
            socket_close($this->socket);
        }
    }

    /**
     * Process the debug server input and output
     * 
     * @return void 
     */
    public function process(EntitiesInterface $entities): void
    {
        if ($this->socket == null) {
            return;
        }

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

        $readingFromClients = $this->clients;

        // check for input
        $changed = socket_select($readingFromClients, $w, $e, 0);

        if (false == $changed) {
            return;
        }

        if ($changed < 1) {
            return;
        }

        // process input
        foreach ($readingFromClients as $i => $socketItem) {
            if ($input = socket_read($socketItem, 16384)) {
                $input = trim($input);

                echo "Client $i: $input\n";

                $response = $this->handleInput($input, $entities);
                $responseJSON = json_encode($response);
                socket_write($socketItem, $responseJSON, strlen($responseJSON));

                if (isset($response['request']) && $response['request'] == "disconnect") {
                    socket_close($socketItem);
                    unset($this->clients[$i]);
                }
            } else {
                // most probably the socket was closed from the client side
                echo "Socket read error: " . socket_strerror(socket_last_error()) . "\n";
                socket_close($socketItem);
                unset($this->clients[$i]);
            }
        }
    }

    /**
     * Handle the input command from the client
     * 
     * @param string $input 
     * @param EntitiesInterface $entities 
     * @return array 
     */
    private function handleInput(string $input, EntitiesInterface $entities): array
    {
        $inputMessageJSON = json_decode($input, true);
        if ($inputMessageJSON == null) {
            return [
                'error' => "Invalid JSON on input"
            ];
        }

        if (!isset($inputMessageJSON['command'])) {
            return [
                'error' => "Missing command"
            ];
        }

        if (!isset($inputMessageJSON['request_id'])) {
            return [
                'error' => "Missing request id for response"
            ];
        }

        $response = [
            'response_id' => $inputMessageJSON['request_id'],
            'request' => $inputMessageJSON['command']
        ];

        switch ($inputMessageJSON['command']) {
            case 'disconnect':
                $response['response'] = "Bye!";
                break;
            case 'available_commands':
                $response['response'] = [
                    "disconnect",
                    "available_commands",
                    "list_transform_components"
                ];
                break;
            case 'list_transform_components':
                $transformComponents = [];
                foreach ($entities->view(Transform::class) as $entity => $transform) {
                    $transformComponents[] = [
                        'entity_id' => $entity,
                        'position' => [
                            'x' => $transform->position->x,
                            'y' => $transform->position->y,
                            'z' => $transform->position->z
                        ]
                    ];
                }
                $response['response'] = $transformComponents;
                break;
            default:
                $response['response'] = "Unknown command";
                break;
        }

        return $response;
    }
}
