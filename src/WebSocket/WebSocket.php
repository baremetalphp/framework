<?php

namespace BareMetalPHP\WebSocket;

/**
 * 
 * Usage:
 *  WebSocket::channel('chat:room:42')
 *   ->type('message')
 *   ->broadcast([
 *       'user_id' => $user->id,
 *      'text'    => $message->body,
 *   ]);
 *
 *   WebSocket::user($user->id)
 *   ->type('notification')
 *   ->broadcast([
 *       'id'    => $notification->id,
 *       'title' => $notification->title,
 *   ]);
 */
class WebSocket
{
    protected string $channel;

    protected ?string $type = null;

    public function __construct(string $channel)
    {
        $this->channel = $channel;
    }

    public static function channel(string $channel): self
    {
        return new self($channel);
    }

    public static function user(string|int $userId): self
    {
        return new self('user:', $userId);
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function broadcast(array $data): void
    {
        $endpoint = getenv('APP_SERVER_WS_PUBLISH_URL') ?: 'http://127.0.0.1:8080/__ws/publish'; // go appserver default

        $payload = json_encode([
            'channel' => $this->channel,
            'type' => $this->type ?? 'event',
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            // log error somewhere centralk
            error_log('WebSocket broadcast: failed to encode payload');
            return;
        }

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1,

        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}