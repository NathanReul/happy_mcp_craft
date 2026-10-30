<?php

declare(strict_types=1);

namespace stimmt\craft\Mcp\transport;

use JsonException;
use Mcp\Schema\JsonRpc\Error;

/**
 * Answers pre-session `server/discover` probes with `METHOD_NOT_FOUND`
 * carrying the caller's request ID.
 *
 * Clients implementing MCP 2026-07-28 stateless discovery (such as
 * Antigravity) probe `server/discover` before `initialize` and fall back to
 * stateful initialization on `-32601` (`Method not found`). `mcp/sdk`
 * instead drops the request ID in `MessageFactory::create()` and replies
 * before session resolution with `-32600` (`Invalid Request`) and `id: ""`,
 * leaving the client waiting forever for a response matching its request ID.
 *
 * @author Max van Essen <support@stimmt.digital>
 */
final class ServerDiscover {
    /**
     * Returns a serialized JSON-RPC `-32601` error when `$payload` is a
     * `server/discover` request with a valid JSON-RPC request ID, or null
     * otherwise.
     */
    public static function reply(string $payload): ?string {
        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (
            !is_array($decoded)
            || array_is_list($decoded)
            || ($decoded['method'] ?? null) !== 'server/discover'
            || !isset($decoded['id'])
            || (!is_int($decoded['id']) && !is_string($decoded['id']))
        ) {
            return null;
        }

        $error = Error::forMethodNotFound('Method "server/discover" not found.', $decoded['id']);

        return json_encode($error, JSON_THROW_ON_ERROR);
    }
}
