# Metadata Reference

Converse Prism automatically extracts and stores metadata from AI responses.

## Automatic Metadata Extraction

When using `addPrismResponse()`, the following metadata is automatically extracted from Prism responses:

### Standard Response Metadata

```php
[
    'model' => 'gpt-4',                    // Model used
    'provider_request_id' => 'req_123',    // Provider's request ID
    'tokens' => 150,                       // Total tokens used
    'prompt_tokens' => 50,                 // Input tokens
    'completion_tokens' => 100,            // Output tokens
    'finish_reason' => 'stop',             // Why generation stopped
]
```

### Tool Call Metadata

For responses with tool calls:

```php
[
    'tool_calls' => [
        [
            'id' => 'call_123',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location": "Tokyo"}'
            ]
        ]
    ]
]
```

### Streaming Metadata

For streamed responses:

```php
[
    'stream_chunks' => 42,                          // Number of chunks
    'stream_duration' => 2.5,                       // Duration in seconds
    'stream_started_at' => '2024-01-01T12:00:00Z', // ISO timestamp
    'stream_completed_at' => '2024-01-01T12:00:02Z', // ISO timestamp
    'stream_status' => 'completed',                 // completed or failed
]
```

## Custom Metadata

You can add custom metadata when storing responses:

```php
$conversation->addPrismResponse($response, [
    'request_id' => Str::uuid(),
    'user_ip' => request()->ip(),
    'session_id' => session()->getId(),
    'custom_field' => 'custom_value'
]);
```

When streaming:

```php
$stream = $conversation->streamPrismResponse([
    'stream_type' => 'sse',
    'client_id' => $clientId
]);
```

## Accessing Metadata

Metadata is stored as JSON and can be accessed like any other message property:

```php
$message = $conversation->lastMessage;

// Access specific metadata
$tokens = $message->metadata['tokens'] ?? 0;
$model = $message->metadata['model'] ?? 'unknown';

// Check if streaming
$wasStreamed = isset($message->metadata['stream_chunks']);

// Get all metadata
$allMetadata = $message->metadata;
```

## Querying by Metadata

Use Laravel's JSON querying capabilities:

```php
// Find messages from a specific model
$gpt4Messages = $conversation->messages()
    ->where('metadata->model', 'gpt-4')
    ->get();

// Find high token usage
$expensiveMessages = $conversation->messages()
    ->where('metadata->tokens', '>', 1000)
    ->get();

// Find streamed messages
$streamedMessages = $conversation->messages()
    ->whereNotNull('metadata->stream_chunks')
    ->get();
```

## Metadata Structure Examples

### Text Response
```json
{
    "model": "claude-3-5-sonnet-latest",
    "provider_request_id": "req_abc123",
    "tokens": 237,
    "prompt_tokens": 125,
    "completion_tokens": 112,
    "finish_reason": "stop"
}
```

### Structured Response
```json
{
    "model": "gpt-4o",
    "provider_request_id": "req_xyz789",
    "tokens": 150,
    "prompt_tokens": 80,
    "completion_tokens": 70,
    "finish_reason": "stop",
    "structured_data": {
        "sentiment": "positive",
        "confidence": 0.95
    }
}
```

### Streaming Response
```json
{
    "model": "claude-3-5-sonnet-latest",
    "provider_request_id": "req_stream123",
    "tokens": 512,
    "prompt_tokens": 200,
    "completion_tokens": 312,
    "finish_reason": "stop",
    "stream_chunks": 45,
    "stream_duration": 3.2,
    "stream_started_at": "2024-01-01T12:00:00.000Z",
    "stream_completed_at": "2024-01-01T12:00:03.200Z",
    "stream_status": "completed"
}
``` 