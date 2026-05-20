<?php
/**
 * WebSocket Server Runner
 * Run this from terminal: php websocket/server.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../websocket/ChatHandler.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MDM\ChatHandler;

echo "===========================================\n";
echo "  MDM Chat WebSocket Server\n";
echo "  Port: " . WEBSOCKET_PORT . "\n";
echo "  Started: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n";

$db     = getDB();
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatHandler($db)
        )
    ),
    WEBSOCKET_PORT,
    WEBSOCKET_HOST
);

echo "Listening on ws://0.0.0.0:" . WEBSOCKET_PORT . "\n\n";
$server->run();
