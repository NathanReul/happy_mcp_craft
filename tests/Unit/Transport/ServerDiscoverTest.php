<?php

declare(strict_types=1);

use Mcp\Schema\JsonRpc\Error;
use Nyholm\Psr7\Factory\Psr17Factory;
use stimmt\craft\Mcp\support\SignalHandler;
use stimmt\craft\Mcp\transport\Buffered;
use stimmt\craft\Mcp\transport\Stdio;

describe('server/discover optional MCP capability probe', function () {
    it('replies with JSON-RPC -32601 Method not found preserving request id on Buffered transport', function () {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'server/discover',
        ], JSON_THROW_ON_ERROR);

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('POST', 'http://localhost/mcp')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody($psr17->createStream($payload));

        $transport = new Buffered($request, psr17: $psr17);
        $transport->initialize();
        $response = $transport->listen();
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        expect($decoded['jsonrpc'])->toBe('2.0')
            ->and($decoded['id'])->toBe(1)
            ->and($decoded['error']['code'])->toBe(Error::METHOD_NOT_FOUND);
    });

    it('replies with JSON-RPC -32601 Method not found preserving request id on Stdio transport', function () {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 42,
            'method' => 'server/discover',
        ], JSON_THROW_ON_ERROR);

        $in = fopen('php://memory', 'r+');
        $out = fopen('php://memory', 'r+');

        $transport = new Stdio(new SignalHandler());
        $inputProp = new ReflectionProperty(Mcp\Server\Transport\StdioTransport::class, 'input');
        $outputProp = new ReflectionProperty(Mcp\Server\Transport\StdioTransport::class, 'output');
        $inputProp->setValue($transport, $in);
        $outputProp->setValue($transport, $out);

        $handleMessage = new ReflectionMethod($transport, 'handleMessage');
        $handleMessage->invoke($transport, $payload, null);

        rewind($out);
        $line = trim((string) stream_get_contents($out));
        fclose($in);
        fclose($out);

        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        expect($decoded['jsonrpc'])->toBe('2.0')
            ->and($decoded['id'])->toBe(42)
            ->and($decoded['error']['code'])->toBe(Error::METHOD_NOT_FOUND);
    });
});
