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

> For core Converse usage (starting conversations, adding messages, etc.), see the [Converse documentation](https://github.com/elliottlawson/converse).

## Making AI Requests

### Text Generation

The most common use case - generating text responses:

```php
$response = $conversation
    ->addUserMessage('Explain quantum computing in simple terms')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(500)
    ->withTemperature(0.7)
    ->asText();

// Store the response
$conversation->addPrismResponse($response);
```

### Using Different Providers

Converse Prism works with any provider supported by Prism:

```php
// OpenAI
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->asText();

// Anthropic
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

// Google
$response = $conversation
    ->toPrismText()
    ->using(Provider::Google, 'gemini-2.0-flash-exp')
    ->asText();

// Groq
$response = $conversation
    ->toPrismText()
    ->using(Provider::Groq, 'llama-3.3-70b-versatile')
    ->asText();
```

### Configuring AI Parameters

Use Prism's fluent API to configure your requests:

```php
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(1000)        // Maximum response length
    ->withTemperature(0.8)       // Creativity (0-2)
    ->withTopP(0.9)             // Nucleus sampling
    ->withPresencePenalty(0.1)   // Discourage repetition
    ->withFrequencyPenalty(0.1)  // Discourage common phrases
    ->withUser('user-123')       // Track user for abuse monitoring
    ->asText();
```

## Handling Responses

### Storing AI Responses

The `addPrismResponse()` method automatically extracts metadata:

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

### Converting to Prism Messages

Get all conversation messages as Prism message objects:

```php
// Get all messages
$prismMessages = $conversation->toPrismMessages();
// Returns: [SystemMessage, UserMessage, AssistantMessage, ...]

// Convert a single message
$message = $conversation->messages()->first();
$prismMessage = $message->toPrismMessage();
// Returns: SystemMessage { content: "You are helpful" }
```

### Manual Conversion

If you need more control, you can manually work with messages:

```php
use Prism\Prism;

// Get messages as Prism objects
$messages = $conversation->toPrismMessages();

// Use them with Prism directly
$response = Prism::text()
    ->withMessages($messages)
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();
```

## Common Patterns

### Question and Answer

```php
$qa = $user->startConversation(['title' => 'Q&A Session'])
    ->addSystemMessage('Answer questions concisely and accurately');

// Ask a question
$response = $qa
    ->addUserMessage('What is the capital of France?')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4o-mini')
    ->withMaxTokens(50)
    ->asText();

$qa->addPrismResponse($response); // "The capital of France is Paris."
```

### Multi-turn Conversation

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

// Second turn - the AI has context from the first turn
$response2 = $chat
    ->addUserMessage('The presentation is about our Q4 results')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

$chat->addPrismResponse($response2);
```

### Different Models for Different Tasks

```php
$conversation = $user->startConversation();

// Use a fast model for simple tasks
$summary = $conversation
    ->addUserMessage('Summarize: ' . $longText)
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4o-mini')
    ->withMaxTokens(150)
    ->asText();

$conversation->addPrismResponse($summary);

// Use a more capable model for complex analysis
$analysis = $conversation
    ->addUserMessage('Now provide a detailed analysis of the key points')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(1000)
    ->asText();

$conversation->addPrismResponse($analysis);
```

## Error Handling

Always wrap AI calls in try-catch blocks:

```php
try {
    $response = $conversation
        ->addUserMessage($userInput)
        ->toPrismText()
        ->using(Provider::OpenAI, 'gpt-4')
        ->asText();
        
            $conversation->addPrismResponse($response);
    
} catch (\Prism\Exceptions\PrismException $e) {
    // Handle Prism-specific errors
    Log::error('AI request failed', [
        'error' => $e->getMessage(),
        'conversation_id' => $conversation->id
    ]);
    
    // Optionally add an error message to the conversation
    $conversation->addAssistantMessage(
        'I apologize, but I encountered an error. Please try again.',
        ['error' => $e->getMessage()]
    );
} catch (\Exception $e) {
    // Handle other errors
    Log::error('Unexpected error', ['error' => $e->getMessage()]);
}
```

## Best Practices

### 1. Always Set a System Message
```php
// Good - Clear context for the AI
$conversation->addSystemMessage('You are a helpful coding assistant specializing in Laravel');

// Less optimal - No context
$conversation->addUserMessage('How do I create a migration?');
```

### 2. Use Appropriate Models
```php
// Simple task = smaller, faster model
->using(Provider::OpenAI, 'gpt-4o-mini')

// Complex task = more capable model
->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
```

### 3. Set Reasonable Token Limits
```php
// For concise responses
->withMaxTokens(150)

// For detailed explanations
->withMaxTokens(1000)

// For long-form content
->withMaxTokens(4000)
```

### 4. Store Metadata for Analytics
```php
$conversation->addPrismResponse($response, [
    'request_duration' => $duration,
    'user_satisfied' => true,
    'category' => 'technical_support'
]);
```

## Next Steps

Now that you understand the basics, explore:

- [Streaming](streaming.md) - Real-time responses for better UX
- [Advanced Features](advanced-features.md) - Tools, structured outputs, embeddings
- [Examples](examples.md) - Real-world implementation patterns 