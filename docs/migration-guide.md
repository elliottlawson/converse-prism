# Migration Guide

This guide helps you migrate from Laravel Converse to Converse-Prism. The good news: **all your existing code continues to work!** Converse-Prism extends Converse, so you can migrate gradually.

## Quick Migration

### 1. Install Converse-Prism

```bash
composer require elliottlawson/converse-prism
```

### 2. Update Your User Model

Replace the Converse trait with the Converse-Prism trait:

```php
// Before
use ElliottLawson\Converse\Traits\HasAIConversations;

// After
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    // ...
}
```

That's it! Your existing code continues to work, and you now have access to all Prism features.

## What Changes?

### Nothing Breaks

All these existing methods continue to work exactly the same:

```php
// Creating conversations - unchanged
$conversation = $user->startConversation(['title' => 'Chat']);

// Adding messages - unchanged
$conversation->addSystemMessage('You are helpful');
$conversation->addUserMessage('Hello');
$conversation->addAssistantMessage('Hi there!');

// Accessing messages - unchanged
$messages = $conversation->messages;
$lastMessage = $conversation->lastMessage;
```

### What's New?

You get additional methods for Prism integration:

```php
// New Prism methods
$conversation->toPrismText();        // Returns PendingTextRequest
$conversation->toPrismStructured();  // Returns PendingStructuredRequest
$conversation->toPrismEmbeddings();  // Returns PendingEmbeddingRequest
$conversation->toPrismMessages();    // Returns array of Prism messages
$conversation->addPrismResponse();   // Adds response with metadata extraction
$conversation->streamPrismResponse(); // Returns PrismStream handler
```

## Gradual Migration Examples

### Stage 1: Keep Your Existing Code

Your current code continues to work:

```php
// Your existing code - still works!
$conversation = $user->startConversation();
$conversation->addUserMessage('What is Laravel?');

// Make your API call however you currently do
$response = $yourAiClient->chat([
    'messages' => $conversation->messages->toArray()
]);

$conversation->addAssistantMessage($response['content']);
```

### Stage 2: Use Prism for New Features

Start using Prism for new conversations while keeping old code:

```php
// New conversations can use Prism
$response = $conversation
    ->addUserMessage('What is Laravel?')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();

$conversation->addPrismResponse($response->text);
```

### Stage 3: Update Existing Features

Gradually update your existing code to use Prism:

```php
// Before - manual API integration
public function chat($message)
{
    $conversation = auth()->user()->startConversation();
    $conversation->addSystemMessage('You are helpful');
    $conversation->addUserMessage($message);
    
    $messages = $conversation->messages->map(function ($msg) {
        return [
            'role' => $msg->role,
            'content' => $msg->content
        ];
    })->toArray();
    
    $response = Http::withToken($this->apiKey)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => $messages
        ]);
        
    $conversation->addAssistantMessage($response->json('choices.0.message.content'));
    
    return $response->json('choices.0.message.content');
}

// After - using Prism
public function chat($message)
{
    $conversation = auth()->user()->startConversation();
    
    $response = $conversation
        ->addSystemMessage('You are helpful')
        ->addUserMessage($message)
        ->toPrismText()
        ->using(Provider::OpenAI, 'gpt-4')
        ->asText();
        
    $conversation->addPrismResponse($response->text);
    
    return $response->text;
}
```

## Common Migration Patterns

### Pattern 1: API Client Replacement

If you're using OpenAI PHP client:

```php
// Before
use OpenAI\Laravel\Facades\OpenAI;

$result = OpenAI::chat()->create([
    'model' => 'gpt-4',
    'messages' => $conversation->messages->map(fn($m) => [
        'role' => $m->role,
        'content' => $m->content
    ])->toArray(),
]);

$conversation->addAssistantMessage($result->choices[0]->message->content);

// After
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();
    
$conversation->addPrismResponse($response->text);
```

### Pattern 2: Streaming Migration

If you have custom streaming:

```php
// Before - complex streaming setup
$stream = OpenAI::chat()->createStreamed([
    'model' => 'gpt-4',
    'messages' => $messages,
    'stream' => true,
]);

$fullResponse = '';
foreach ($stream as $chunk) {
    $delta = $chunk->choices[0]->delta->content ?? '';
    $fullResponse .= $delta;
    echo $delta;
}

$conversation->addAssistantMessage($fullResponse);

// After - simplified streaming
$stream = $conversation->streamPrismResponse();

$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
        echo $chunk;
    });

$stream->complete($response);
```

### Pattern 3: Multi-Provider Support

If you want to support multiple providers:

```php
// Before - provider-specific code
if ($provider === 'openai') {
    $response = $this->callOpenAI($conversation);
} elseif ($provider === 'anthropic') {
    $response = $this->callAnthropic($conversation);
}

// After - unified interface
$provider = match($providerName) {
    'openai' => Provider::OpenAI,
    'anthropic' => Provider::Anthropic,
    'google' => Provider::Google,
};

$response = $conversation
    ->toPrismText()
    ->using($provider, $model)
    ->asText();
```

## Feature Comparison

| Feature | Laravel Converse | Converse-Prism |
|---------|-----------------|----------------|
| Database storage | ✅ | ✅ |
| Message management | ✅ | ✅ |
| Events | ✅ | ✅ |
| Provider agnostic | ✅ | ✅ |
| Direct AI integration | ❌ | ✅ |
| Automatic message formatting | ❌ | ✅ |
| Built-in streaming | ❌ | ✅ |
| Metadata extraction | Manual | Automatic |
| Multiple providers | Manual setup | Built-in |
| Tool calling | Manual | Built-in |
| Structured output | Manual | Built-in |

## Migration Checklist

- [ ] Install Converse-Prism package
- [ ] Update User model trait
- [ ] Test existing functionality still works
- [ ] Identify features to migrate first
- [ ] Update new features to use Prism
- [ ] Gradually migrate existing features
- [ ] Remove old AI client code
- [ ] Update tests

## Handling Edge Cases

### Custom Message Types

If you have custom message types:

```php
// Your custom types still work
$conversation->messages()->create([
    'role' => 'custom_role',
    'content' => 'Custom content',
    'metadata' => ['custom' => 'data']
]);

// But now you can also use Prism features
$conversation->addUserMessage('Regular message')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();
```

### Custom Conversation Models

If you've extended the Conversation model:

```php
// Your custom model
class CustomConversation extends Conversation
{
    // Your customizations
}

// Update your User model
class User extends Authenticatable
{
    use HasAIConversations;
    
    protected function conversationModel(): string
    {
        return CustomConversation::class;
    }
}
```

### Database Considerations

No database changes are required! Converse-Prism uses the same tables:
- `conversations` table - unchanged
- `messages` table - unchanged

The only difference is that Prism metadata is automatically stored in the existing `metadata` JSON column.

## Performance Benefits

After migration, you'll see improvements in:

1. **Less code** - Remove boilerplate API integration
2. **Automatic retries** - Built into Prism
3. **Better error handling** - Unified exception handling
4. **Streaming performance** - Optimized chunk handling
5. **Memory efficiency** - Prism handles large responses efficiently

## Getting Help

- Check the [Examples](examples.md) for migration patterns
- Review the [API Reference](api-reference.md) for new methods
- Open an issue on [GitHub](https://github.com/elliottlawson/converse-prism) for specific migration questions

## Summary

Migrating to Converse-Prism is designed to be painless:

1. **Nothing breaks** - All existing code continues to work
2. **Gradual adoption** - Migrate at your own pace
3. **Immediate benefits** - Use new features right away
4. **Less code** - Simplify your AI integrations
5. **More features** - Access streaming, tools, structured output, and more

Start by installing the package and trying out the new methods on a single conversation. You'll quickly see how much simpler your AI integration becomes! 