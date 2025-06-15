# Conversations API Reference

Extensions to the Conversation model for Prism PHP integration.

## Methods

### `toPrismText()`

Returns a `PendingTextRequest` with conversation messages pre-loaded.

```php
public function toPrismText(): \Prism\Prism\Text\PendingRequest
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

### `toPrismStructured()`

Returns a `PendingStructuredRequest` for schema-based responses.

```php
public function toPrismStructured(): \Prism\Prism\Structured\PendingRequest
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

### `toPrismEmbeddings()`

Returns a `PendingEmbeddingRequest` for generating embeddings from the last user message.

```php
public function toPrismEmbeddings(): \Prism\Prism\Embeddings\PendingRequest
```

**Returns:** `PendingEmbeddingRequest` - Ready for embedding generation

**Example:**
```php
$embeddings = $conversation->toPrismEmbeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->asEmbeddings();
```

---

### `toPrismMessages()`

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

### `addPrismResponse()`

Adds an AI response with automatic metadata extraction.

```php
public function addPrismResponse($response, array $metadata = []): self
```

**Parameters:**
- `$response` - The Prism response object (automatically extracts text and metadata)
- `$metadata` - Additional metadata to store

**Returns:** `self` - For method chaining

**Automatic Metadata Extraction:**
- `model` - The model used
- `provider_request_id` - Provider's request ID
- `tokens` - Total token count
- `prompt_tokens` - Input token count
- `completion_tokens` - Output token count
- `finish_reason` - Why generation stopped
- `tool_calls` - For tool call responses

**Example:**
```php
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

$conversation->addPrismResponse($response, [
    'custom_field' => 'value'
]);
```

---

### `streamPrismResponse()`

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

// Use in streaming callback
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
    });

$message = $stream->complete($response);
```

## Inherited Methods

All standard Converse conversation methods remain available:

```php
// Message management
$conversation->addSystemMessage(string $content, array $metadata = []): self
$conversation->addUserMessage(string $content, array $metadata = []): self
$conversation->addAssistantMessage(string $content, array $metadata = []): self
$conversation->addToolCallMessage(string $content, array $metadata = []): self
$conversation->addToolResultMessage(string $content, array $metadata = []): self

// Message retrieval
$conversation->messages(): HasMany
$conversation->lastMessage(): ?Message
```

See the [Converse documentation](https://converse-php.netlify.app/api/conversations) for complete details on inherited methods. 