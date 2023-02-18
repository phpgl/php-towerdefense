<?php

namespace TowerDefense\Debug;

use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Runtime\DebugConsole;
use VISU\Signal\Dispatcher;
use VISU\Signals\Runtime\ConsoleCommandSignal;

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

    private Dispatcher $dispatcher; // the signal dispatcher
    private ?DebugConsole $console = null; // the optional debug console

    /**
     * DebugServer constructor.
     * 
     * @param string $address 
     * @param int $port 
     * @return void 
     */
    public function __construct(string $address, int $port, Dispatcher $dispatcher)
    {
        $this->address = $address;
        $this->port = $port;
        $this->dispatcher = $dispatcher;
    }

    public function setDebugConsole(DebugConsole $console): void
    {
        $this->console = $console;
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

                // check for disconnect
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
        // parse the input
        $inputMessageJSON = json_decode($input, true);
        if ($inputMessageJSON == null) {
            // invalid JSON
            return [
                'error' => "Invalid JSON on input"
            ];
        }

        // check for the command
        if (!isset($inputMessageJSON['command'])) {
            return [
                'error' => "Missing command"
            ];
        }

        // check for the request id
        if (!isset($inputMessageJSON['request_id'])) {
            return [
                'error' => "Missing request id for response"
            ];
        }

        // prepare the response
        $response = [
            'response_id' => $inputMessageJSON['request_id'],
            'request' => $inputMessageJSON['command']
        ];

        // handle the command
        switch ($inputMessageJSON['command']) {
            case 'disconnect':
                // close the socket
                $response['response'] = "Bye!";
                break;
            case 'available_commands':
                // return the available commands
                $response['response'] = [
                    "disconnect",
                    "available_commands",
                    "list_transform_components",
                    "console_command"
                ];
                break;
            case 'list_transform_components':
                // return the list of transform components, their connected entity ids and their positions
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
            case 'console_command':
                // trigger a console command
                if ($this->console == null) {
                    // debug console not available
                    $response['error'] = "Debug console not available";
                    break;
                }
                if (!isset($inputMessageJSON['console_command']) || strlen($inputMessageJSON['console_command']) < 1) {
                    // missing console command
                    $response['error'] = "Missing console command";
                    break;
                }
                // dispatch the console command signal
                $commandSignal = new ConsoleCommandSignal($inputMessageJSON['console_command'], $this->console);
                $this->dispatcher->dispatch(DebugConsole::EVENT_CONSOLE_COMMAND, $commandSignal);
                $response['response'] = "Console command triggered";
                break;
            default:
                // unknown command
                $response['error'] = "Unknown command";
                break;
        }

        return $response;
    }
}
