# Metadata API Reference

Automatic metadata extraction and structure for AI responses.

## Overview

Converse Prism automatically extracts and stores metadata from Prism responses, providing detailed information about each AI interaction.

## Standard Metadata

### Response Metadata

When using `addPrismResponse()`, the following metadata is automatically extracted:

```php
[
    'model' => 'gpt-4',                    // Model used
    'provider_request_id' => 'req_123',    // Provider's request ID
    'tokens' => 150,                       // Total tokens used
    'prompt_tokens' => 50,                 // Input token count
    'completion_tokens' => 100,            // Output token count
    'finish_reason' => 'stop',             // Why generation stopped
    'steps' => [...],                      // Multi-step responses (if applicable)
    // Plus any custom metadata you provide
]
```

### Streaming Metadata

For streamed responses using `PrismStream`, additional metadata is captured:

```php
[
    // All standard response metadata plus:
    'stream_chunks' => 42,                 // Number of chunks received
    'stream_duration' => 2.5,              // Total duration in seconds
    'stream_started_at' => '2024-01-01...', // ISO 8601 timestamp
    'stream_completed_at' => '2024-01-01...', // ISO 8601 timestamp
]
```

### Tool Call Metadata

For responses with tool calls:

```php
[
    'tool_calls' => [
        [
            'id' => 'call_abc123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location": "London"}'
            ]
        ]
    ],
    // Plus standard metadata
]
```

## Custom Metadata

Add custom metadata alongside automatic extraction:

```php
$conversation->addPrismResponse($response, [
    'session_id' => session()->getId(),
    'user_timezone' => 'America/New_York',
    'custom_tags' => ['support', 'technical'],
    'priority' => 'high'
]);
```

## Accessing Metadata

Metadata is stored as JSON and can be accessed via the message:

```php
$message = $conversation->lastAssistantMessage();
$metadata = $message->metadata;

// Access specific fields
$model = $metadata['model'] ?? null;
$tokens = $metadata['tokens'] ?? 0;

// Check streaming info
if (isset($metadata['stream_duration'])) {
    $duration = $metadata['stream_duration'];
    $chunks = $metadata['stream_chunks'];
}
```

## Metadata Preservation

When using streaming, metadata is preserved throughout the process:

```php
// Initial metadata
$stream = $conversation->streamPrismResponse([
    'request_source' => 'api',
    'client_version' => '1.0'
]);

// Automatic extraction on completion
$message = $stream->complete($response);

// All metadata is merged:
// - Initial metadata
// - Streaming metadata
// - Response metadata
```

## Error Metadata

Failed operations include error details:

```php
$stream->fail('Connection timeout', [
    'provider' => 'openai',
    'attempted_model' => 'gpt-4',
    'retry_count' => 3,
    'last_error_code' => 'timeout'
]);

// Resulting metadata:
[
    'error' => 'Connection timeout',
    'provider' => 'openai',
    'attempted_model' => 'gpt-4',
    'retry_count' => 3,
    'last_error_code' => 'timeout',
    'stream_failed_at' => '2024-01-01...'
]
```

## Querying by Metadata

Use Laravel's JSON queries to filter messages:

```php
// Find high-token messages
$expensiveMessages = Message::where('metadata->tokens', '>', 1000)->get();

// Find messages by model
$gpt4Messages = Message::where('metadata->model', 'gpt-4')->get();

// Find failed streams
$failedStreams = Message::whereNotNull('metadata->error')->get();

// Complex queries
$recentOpenAI = Message::where('metadata->model', 'like', 'gpt-%')
    ->where('created_at', '>', now()->subDay())
    ->get();
```

## Best Practices

1. **Don't Override System Metadata**: Avoid using keys that Converse Prism uses automatically (`model`, `tokens`, etc.)

2. **Use Consistent Keys**: Establish naming conventions for custom metadata fields

3. **Index Important Fields**: For frequently queried metadata, consider database indexes:
   ```sql
   CREATE INDEX messages_metadata_model ON messages ((metadata->>'model'));
   ```

4. **Keep Metadata Lightweight**: Metadata is stored as JSON, so avoid storing large objects 