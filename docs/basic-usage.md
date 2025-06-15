# Basic Usage

This guide covers the core features of Converse-Prism and common usage patterns.

## The Magic: Automatic Message Passing

The most powerful feature of Converse-Prism is **automatic message passing**. When you call `toPrismText()`, `toPrismStructured()`, or `toPrismEmbeddings()`, the package automatically:

1. Retrieves all messages from the conversation
2. Converts them to Prism's message format
3. Passes them to the Prism request
4. Returns a configured request ready for additional options

```php
// What you write:
$request = $conversation->toPrismText();

// What happens behind the scenes:
$messages = $conversation->toPrismMessages(); // Convert to Prism format
$request = Prism::text()->withMessages($messages); // Pass to Prism
```

This eliminates the boilerplate of manually extracting and formatting messages for every API call.

## Starting Conversations

### Creating a New Conversation

```php
use Prism\Enums\Provider;

$user = auth()->user();

// Simple conversation
$conversation = $user->startConversation();

// With metadata
$conversation = $user->startConversation([
    'title' => 'Product Support Chat',
    'metadata' => [
        'source' => 'web',
        'category' => 'support',
        'user_timezone' => 'America/New_York'
    ]
]);
```

### Continuing Existing Conversations

```php
// Find by ID
$conversation = $user->conversations()->find($conversationId);

// Find by title
$conversation = $user->conversations()
    ->where('title', 'Product Support Chat')
    ->first();

// Get the most recent conversation
$conversation = $user->conversations()->latest()->first();
```

## Adding Messages

### Message Types

Converse-Prism supports all standard message roles:

```php
// System message - Sets the AI's behavior
$conversation->addSystemMessage('You are a helpful customer support agent');

// User message - What the user says
$conversation->addUserMessage('I need help with my order');

// Assistant message - The AI's response (usually added automatically)
$conversation->addAssistantMessage('I\'d be happy to help with your order');

// Tool call message - When the AI wants to use a tool
$conversation->addToolCallMessage('[{"name": "get_order", "arguments": {"id": "123"}}]');

// Tool result message - The result of a tool call
$conversation->addToolResultMessage('{"status": "shipped", "tracking": "ABC123"}');
```

### Chaining Messages

All message methods return the conversation instance for chaining:

```php
$conversation = $user->startConversation()
    ->addSystemMessage('You are a Python expert')
    ->addUserMessage('How do I read a CSV file?')
    ->addUserMessage('Using pandas specifically');
```

### Messages with Metadata

Add custom metadata to any message:

```php
$conversation->addUserMessage('What is the weather?', [
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'timestamp' => now()->toIso8601String()
]);
```

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
$conversation->addPrismResponse($response->text);
```

### Using Different Providers

Converse-Prism works with any provider supported by Prism:

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
$conversation->addPrismResponse($response->text);

// With additional custom metadata
$conversation->addPrismResponse($response->text, [
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

$qa->addPrismResponse($response->text); // "The capital of France is Paris."
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

$chat->addPrismResponse($response1->text);

// Second turn - the AI has context from the first turn
$response2 = $chat
    ->addUserMessage('The presentation is about our Q4 results')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->asText();

$chat->addPrismResponse($response2->text);
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

$conversation->addPrismResponse($summary->text);

// Use a more capable model for complex analysis
$analysis = $conversation
    ->addUserMessage('Now provide a detailed analysis of the key points')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(1000)
    ->asText();

$conversation->addPrismResponse($analysis->text);
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
        
    $conversation->addPrismResponse($response->text);
    
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
$conversation->addPrismResponse($response->text, [
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