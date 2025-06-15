# PrismStream API Reference

The streaming response handler for real-time AI responses.

## Overview

PrismStream manages streaming responses, handling chunk accumulation, metadata tracking, and message finalization.

## Methods

### `append()`

Appends a chunk to the streaming message.

```php
public function append(string $chunk): self
```

**Parameters:**
- `$chunk` - Text chunk to append

**Returns:** `self` - For method chaining

**Example:**
```php
$stream = $conversation->streamPrismResponse();

// In streaming callback
$stream->append($chunk);
```

---

### `complete()`

Completes the stream and finalizes the message with metadata.

```php
public function complete($prismResponse = null): Message
```

**Parameters:**
- `$prismResponse` - Optional Prism response for metadata extraction

**Returns:** `Message` - The completed message with streaming metadata

**Automatic Metadata:**
- All standard response metadata (model, tokens, etc.)
- `stream_chunks` - Number of chunks received
- `stream_duration` - Total streaming duration in seconds
- `stream_started_at` - ISO timestamp when streaming began
- `stream_completed_at` - ISO timestamp when streaming finished

**Example:**
```php
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
        echo $chunk; // Real-time output
    });

$message = $stream->complete($response);
```

---

### `fail()`

Marks the stream as failed with error details.

```php
public function fail(string $error, array $errorMetadata = []): Message
```

**Parameters:**
- `$error` - Error message
- `$errorMetadata` - Additional error context

**Returns:** `Message` - The failed message with error metadata

**Example:**
```php
try {
    // Streaming operation
} catch (\Exception $e) {
    $message = $stream->fail($e->getMessage(), [
        'exception_class' => get_class($e),
        'trace' => $e->getTraceAsString()
    ]);
}
```

---

### `getMessage()`

Gets the underlying message being streamed.

```php
public function getMessage(): Message
```

**Returns:** `Message` - The current message instance

**Example:**
```php
$message = $stream->getMessage();
$currentContent = $message->content;
```

## Usage Pattern

```php
// 1. Create stream
$stream = $conversation->streamPrismResponse([
    'session_id' => session()->getId()
]);

// 2. Stream response
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->stream(function ($chunk) use ($stream) {
        // Append chunk
        $stream->append($chunk);
        
        // Real-time processing
        broadcast(new MessageChunk($chunk));
    });

// 3. Complete stream
$message = $stream->complete($response);
```

## Error Handling

Always wrap streaming operations in try-catch blocks:

```php
$stream = $conversation->streamPrismResponse();

try {
    $response = $conversation
        ->toPrismText()
        ->using($provider, $model)
        ->stream(function ($chunk) use ($stream) {
            $stream->append($chunk);
        });
    
    $message = $stream->complete($response);
} catch (\Exception $e) {
    $message = $stream->fail(
        'Streaming failed: ' . $e->getMessage(),
        ['error_code' => $e->getCode()]
    );
} 