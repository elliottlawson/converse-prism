# API Reference

Complete reference for all Converse Prism methods and classes.

## Conversation Methods

### Core Prism Integration

#### `toPrismText()`
Returns a `PendingTextRequest` with conversation messages pre-loaded.

```php
public function toPrismText(): \EchoLabs\Prism\Requests\PendingTextRequest
```

**Returns:** `PendingTextRequest` - Ready for provider/model configuration

**Example:**
```php
$request = $conversation->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(500)
    ->asText();
```

---

#### `toPrismStructured()`
Returns a `PendingStructuredRequest` for schema-based responses.

```php
public function toPrismStructured(): \EchoLabs\Prism\Requests\PendingStructuredRequest
```

**Returns:** `PendingStructuredRequest` - Ready for schema configuration

**Example:**
```php
$response = $conversation->toPrismStructured()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withSchema($schema)
    ->asStructured();
```

---

#### `toPrismEmbeddings()`
Returns a `PendingEmbeddingRequest` for generating embeddings.

```php
public function toPrismEmbeddings(): \EchoLabs\Prism\Requests\PendingEmbeddingRequest
```

**Returns:** `PendingEmbeddingRequest` - Ready for embedding generation

**Example:**
```php
$embeddings = $conversation->toPrismEmbeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->asEmbeddings();
```

---

#### `toPrismMessages()`
Converts all conversation messages to Prism message objects.

```php
public function toPrismMessages(): array
```

**Returns:** `array` - Array of Prism message objects

**Example:**
```php
$messages = $conversation->toPrismMessages();
// [SystemMessage, UserMessage, AssistantMessage, ...]
```

---

#### `addPrismResponse()`
Adds an AI response with automatic metadata extraction.

```php
public function addPrismResponse(
    string|array $content, 
    array $metadata = [], 
    ?object $prismResponse = null
): self
```

**Parameters:**
- `$content` - The response content (string or array for structured)
- `$metadata` - Additional metadata to store
- `$prismResponse` - Optional Prism response object for metadata extraction

**Returns:** `self` - For method chaining

**Example:**
```php
$conversation->addPrismResponse($response->text, [
    'custom_field' => 'value'
]);
```

---

#### `streamPrismResponse()`
Creates a new streaming response handler.

```php
public function streamPrismResponse(array $metadata = []): PrismStream
```

**Parameters:**
- `$metadata` - Initial metadata for the streaming message

**Returns:** `PrismStream` - Stream handler instance

**Example:**
```php
$stream = $conversation->streamPrismResponse([
    'session_id' => session()->getId()
]);
```

## Message Methods

### Conversion

#### `toPrismMessage()`
Converts a single message to its Prism equivalent.

```php
public function toPrismMessage(): mixed
```

**Returns:** Prism message object (SystemMessage, UserMessage, etc.)

**Example:**
```php
$prismMessage = $message->toPrismMessage();
// Returns appropriate Prism message type
```

## PrismStream Class

### Methods

#### `append()`
Appends a chunk to the streaming message.

```php
public function append(string $chunk): self
```

**Parameters:**
- `$chunk` - Text chunk to append

**Returns:** `self` - For method chaining

---

#### `complete()`
Completes the stream and finalizes the message.

```php
public function complete(?object $prismResponse = null): Message
```

**Parameters:**
- `$prismResponse` - Optional Prism response for metadata extraction

**Returns:** `Message` - The completed message

**Example:**
```php
$message = $stream->complete($response);
```

---

#### `fail()`
Marks the stream as failed with error details.

```php
public function fail(string $error, array $errorMetadata = []): Message
```

**Parameters:**
- `$error` - Error message
- `$errorMetadata` - Additional error context

**Returns:** `Message` - The failed message

---

#### `getMessage()`
Gets the underlying message being streamed.

```php
public function getMessage(): Message
```

**Returns:** `Message` - The current message instance

## Inherited Methods

All standard Converse methods remain available:

### Message Management

```php
// Add messages
$conversation->addSystemMessage(string $content, array $metadata = []): self
$conversation->addUserMessage(string $content, array $metadata = []): self
$conversation->addAssistantMessage(string $content, array $metadata = []): self
$conversation->addToolCallMessage(string $content, array $metadata = []): self
$conversation->addToolResultMessage(string $content, array $metadata = []): self

// Message retrieval
$conversation->messages(): HasMany
$conversation->getMessages(): Collection
$conversation->lastMessage(): ?Message
$conversation->lastUserMessage(): ?Message
$conversation->lastAssistantMessage(): ?Message
```

### Conversation Management

```php
// User's conversations
$user->conversations(): HasMany
$user->startConversation(array $attributes = []): Conversation

// Conversation operations
$conversation->delete(): bool
$conversation->archive(): self
$conversation->unarchive(): self
$conversation->updateTitle(string $title): self
```

## Metadata Structure

### Automatic Metadata Extraction

When using `addPrismResponse()`, the following metadata is automatically extracted:

```php
[
    'model' => 'gpt-4',                    // Model used
    'provider_request_id' => 'req_123',    // Provider's request ID
    'tokens' => 150,                       // Total tokens
    'prompt_tokens' => 50,                 // Input tokens
    'completion_tokens' => 100,            // Output tokens
    'finish_reason' => 'stop',             // Why generation stopped
    'steps' => [...],                      // Multi-step responses
    // Plus any custom metadata you provide
]
```

### Streaming Metadata

For streamed responses:

```php
[
    'stream_chunks' => 42,                 // Number of chunks
    'stream_duration' => 2.5,              // Duration in seconds
    'stream_started_at' => '2024-01-01...', // ISO timestamp
    'stream_completed_at' => '2024-01-01...', // ISO timestamp
    // Plus standard metadata
]
```

## Type Definitions

### Message Roles

```php
const ROLES = [
    'system',        // System instructions
    'user',          // User input
    'assistant',     // AI response
    'tool_call',     // Tool invocation
    'tool_result'    // Tool response
];
```

### Prism Message Types

The package automatically converts to these Prism types:

- `\EchoLabs\Prism\Messages\SystemMessage`
- `\EchoLabs\Prism\Messages\UserMessage`
- `\EchoLabs\Prism\Messages\AssistantMessage`
- `\EchoLabs\Prism\Messages\ToolResultMessage`

## Events

Converse Prism triggers all standard Converse events:

```php
// Conversation events
ConversationStarted::class
ConversationDeleted::class

// Message events
MessageCreated::class
MessageUpdated::class
MessageDeleted::class
```

## Error Handling

### Common Exceptions

```php
try {
    $response = $conversation->toPrismText()
        ->using(Provider::OpenAI, 'gpt-4')
        ->asText();
} catch (\EchoLabs\Prism\Exceptions\PrismException $e) {
    // Handle Prism-specific errors
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    // Handle missing conversation/message
} catch (\Exception $e) {
    // Handle general errors
}
```

### Stream Error Handling

```php
$stream = $conversation->streamPrismResponse();

try {
    // Streaming operation
} catch (\Exception $e) {
    $stream->fail($e->getMessage(), [
        'exception_class' => get_class($e),
        'trace' => $e->getTraceAsString()
    ]);
}
```

## Configuration

### Package Configuration

No additional configuration is required beyond standard Converse and Prism setup.

### Model Customization

To use custom models, update your trait usage:

```php
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    
    // Override model classes if needed
    protected function conversationModel(): string
    {
        return \App\Models\CustomConversation::class;
    }
}
```

## Performance Considerations

### Efficient Message Loading

```php
// Eager load messages to avoid N+1
$conversation = $user->conversations()
    ->with('messages')
    ->find($id);

// Limit message history
$recentMessages = $conversation->messages()
    ->latest()
    ->take(10)
    ->get()
    ->reverse();
```

### Token Optimization

```php
// Count tokens before sending
$messageCount = $conversation->messages()->count();
$approximateTokens = $messageCount * 50; // Rough estimate

if ($approximateTokens > 3000) {
    // Trim older messages or summarize
}
```

## Version Compatibility

- **PHP:** 8.2+
- **Laravel:** 11.0+ or 12.0+
- **Converse:** ^0.1
- **Prism PHP:** ^0.71

## Support

For issues or questions:
- [GitHub Issues](https://github.com/elliottlawson/converse-prism/issues)
- [Converse Docs](https://converse-php.netlify.app)
- [Prism PHP Docs](https://github.com/echolabsdev/prism) 