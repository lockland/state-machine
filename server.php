<?php
define("INITIAL_STATE", 0);
define("LINK_DOWN", 1);
define("ENVIANDO_START", 2);
define("START_RECEBIDO", 3);
define("LINK_OK", 4);
define("ERROR_MSG", "Invalid event resetting");

function sig_handler($signo)
{
    global $socket;
    socket_close($socket);
    exit;
}

function p($msg)
{
    echo "$msg\n";
}

function changeState(&$state, $event)
{
    switch($state) {
    case INITIAL_STATE:
        if (LINK_DOWN == $event) {
            $state = LINK_DOWN;
            $m = "Link down";
        } else {
            $state = INITIAL_STATE;
            $m = ERROR_MSG;
        }

        return $m;

    case LINK_DOWN:
        if (ENVIANDO_START == $event) {
            $state = ENVIANDO_START;
            $m = "Enviando Start";
        } else {
            $state = INITIAL_STATE;
            $m = ERROR_MSG;
        }
        return $m;

    case ENVIANDO_START:
        if (START_RECEBIDO == $event) {
            $state = START_RECEBIDO;
            $m = "Start recebido envia configuracao";
        } else {
            $state = INITIAL_STATE;
            $m = ERROR_MSG;
        }

        return $m;

    case START_RECEBIDO:
        if (LINK_OK == $event) {
            $state = LINK_OK;
            $m = "Link OK - manda keepalive";
        } else {
            $state = INITIAL_STATE;
            $m = ERROR_MSG;
        }

        return $m;

    case LINK_OK:
        return "Link is already OK";

    default:
        $state = INITIAL_STATE;
        return "Invalid event resetting";
    }
}

set_time_limit(0);
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");


$host = "127.0.0.1";
$port = 3000;
$socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\n");
socket_bind($socket, $host, $port) or die("Could not bind to socket\n");
socket_listen($socket, 3) or die("Could not set up socket listener\n");

$states = [
    [0, 0, 0, 0, 0],
    [1, 2, 1, 1, 1],
    [1, 0, 3, 2, 2],
    [1, 0, 4, 3, 2],
    [1, 0, 4, 2, 2],
];

$state = INITIAL_STATE;

while (1) {
    p("Accepting Connection on $host:$port");
    $conn = socket_accept($socket) or die("Could not accept incoming connection\n");
    socket_getsockname($socket, $addr);
    p("Client $addr has connected");
    while (($input = @socket_read($conn, 1024)) !== false) {
        $input = trim($input);

        if ("" === $input) {
            p("Could not read input");
            $output = "Could not process a empty request";
            @socket_write($conn, $output, strlen($output));
            continue;
        }

        if ('exit' == $input) {
            p("Client $addr disconnected");
            break;
        }

        $output = changeState($state, $input);
        p($output . ":" . json_encode($states[$state]));
        socket_write($conn, $output, strlen ($output)) or error_log("Could not write output\n");
        sleep(1);
    }

    socket_close($conn);
}
socket_close($socket);
