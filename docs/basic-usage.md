# Basic Usage

This guide covers the core features of Converse Prism and common usage patterns.

## The Magic: Automatic Message Passing

Converse Prism seamlessly bridges Laravel Converse with Prism PHP. It allows you to not only store and retrieve conversation history but also fluently pass that history into a Prism request chain.

When you call `toPrismText()`, `toPrismStructured()`, or `toPrismEmbeddings()` on a conversation, the entire message history is automatically converted to Prism's format and injected into your request:

```php
// Get a conversation and add a message
$conversation = $user->conversations()->find($id);

$response = $conversation
    ->addUserMessage('Explain quantum computing')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(500)
    ->asText();

// Store the response with metadata
$conversation->addPrismResponse($response);
```

This eliminates the boilerplate of manually extracting and formatting messages for every API call.

> For core Converse usage (starting conversations, adding messages, etc.), see the [Converse documentation](https://converse-php.netlify.app/guide/conversations.html).

## Making AI Requests

### Text Generation

Generate text responses with conversation history automatically included:

```php
$response = $conversation
    ->addUserMessage('Explain quantum computing in simple terms')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(500)
    ->withTemperature(0.7)
    ->asText();

$conversation->addPrismResponse($response);
```

### Structured Output

Generate structured responses:

```php
$response = $conversation
    ->addUserMessage('Extract the key points from our discussion')
    ->toPrismStructured()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withStructure(['key_points' => 'array'])
    ->asStructured();

$conversation->addPrismResponse($response);
```

### Embeddings

Generate embeddings from the last user message:

```php
$response = $conversation
    ->addUserMessage('Machine learning is transforming industries')
    ->toPrismEmbeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->asVector();

// Store with custom metadata
$conversation->addAssistantMessage('Embedding generated', [
    'embedding' => $response->embedding,
    'model' => 'text-embedding-3-small'
]);
```

## Storing Responses

The `addPrismResponse()` method automatically extracts metadata from Prism responses:

```php
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

// Automatically extracts: model, tokens, finish_reason, etc.
$conversation->addPrismResponse($response);

// With additional custom metadata
$conversation->addPrismResponse($response, [
    'request_id' => Str::uuid(),
    'response_time' => $responseTime
]);
```

### Accessing Response Metadata

The stored message includes all extracted metadata:

```php
$lastMessage = $conversation->lastMessage;

echo $lastMessage->content;                          // The AI's response text
echo $lastMessage->metadata['model'];                // claude-3-5-sonnet-latest
echo $lastMessage->metadata['tokens'];               // Total tokens used
echo $lastMessage->metadata['prompt_tokens'];        // Input tokens
echo $lastMessage->metadata['completion_tokens'];    // Output tokens
echo $lastMessage->metadata['finish_reason'];        // Why generation stopped
```

## Message Format Conversion

### Converting Messages to Prism Format

The `toPrismMessages()` method converts all conversation messages to Prism message objects:

```php
// Get all messages as Prism objects
$prismMessages = $conversation->toPrismMessages();
// Returns: [SystemMessage, UserMessage, AssistantMessage, ...]

// Each message is properly formatted for Prism
foreach ($prismMessages as $message) {
    echo get_class($message); // Prism\Prism\Messages\UserMessage, etc.
    echo $message->content;
}
```

### Single Message Conversion

Convert individual messages:

```php
$message = $conversation->messages()->first();
$prismMessage = $message->toPrismMessage();
// Returns: SystemMessage { content: "You are helpful" }
```

### Manual Usage

If you need more control, you can use the converted messages directly with Prism:

```php
use Prism\Prism;

// Get messages as Prism objects
$messages = $conversation->toPrismMessages();

// Use them with Prism directly
$response = Prism::text()
    ->withMessages($messages)
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();

// Add custom messages before sending
$messages[] = new \Prism\Prism\Messages\UserMessage('One more thing...');
$response = Prism::text()
    ->withMessages($messages)
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();
```

## Multi-turn Conversations

The real power comes from maintaining context across multiple turns:

```php
$chat = $user->startConversation(['title' => 'Planning Assistant'])
    ->addSystemMessage('You help users plan their day efficiently');

// First turn
$response1 = $chat
    ->addUserMessage('I have 3 meetings today and need to prepare a presentation')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

$chat->addPrismResponse($response1);

// Second turn - the AI has context from the entire conversation
$response2 = $chat
    ->addUserMessage('The presentation is about our Q4 results')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

$chat->addPrismResponse($response2);
```

## Next Steps

Now that you understand the basics, explore:

- [Streaming](streaming.md) - Real-time responses for better UX
- [Advanced Features](advanced-features.md) - Tools, structured outputs, embeddings
- [Examples](examples.md) - Real-world implementation patterns 