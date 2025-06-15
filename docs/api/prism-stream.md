# PrismStream API Reference

The `PrismStream` class handles streaming responses from AI providers.

## Class: `ElliottLawson\ConversePrism\Support\PrismStream`

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

// In your streaming callback
$stream->append($chunk);
```

---

### `complete()`

Completes the stream and finalizes the message with metadata extraction.

```php
public function complete($response = null): Message
```

**Parameters:**
- `$response` - Optional Prism response object for metadata extraction

**Returns:** `Message` - The completed message with extracted metadata

**Metadata Added:**
- `stream_chunks` - Number of chunks received
- `stream_duration` - Total streaming time in seconds
- `stream_started_at` - ISO timestamp when streaming started
- `stream_completed_at` - ISO timestamp when streaming completed
- Plus all standard response metadata (tokens, model, etc.)

**Example:**
```php
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
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
    $stream->fail($e->getMessage(), [
        'error_type' => 'api_error',
        'exception_class' => get_class($e)
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
echo $message->id; // Access message properties during streaming
```

## Usage Pattern

```php
// 1. Create stream
$stream = $conversation->streamPrismResponse();

// 2. Stream chunks
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        // Append each chunk
        $stream->append($chunk);
        
        // Optionally output to user
        echo $chunk;
    });

// 3. Complete or fail
try {
    $message = $stream->complete($response);
} catch (\Exception $e) {
    $message = $stream->fail($e->getMessage());
}
``` 