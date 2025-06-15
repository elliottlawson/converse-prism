# Migration from Converse

Migrating from Converse to Converse Prism is simple - just change one line of code.

## The One-Line Migration

Update your User model to use the Converse Prism trait:

```php
// Before
use ElliottLawson\Converse\Traits\HasAIConversations;

// After
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
}
```

That's it! **All your existing code continues to work exactly as before.**

## What You Get

After changing the trait, you automatically gain access to Prism integration methods:

```php
// New methods available on your conversations
$conversation->toPrismText()        // Get a Prism text request
$conversation->toPrismStructured()  // Get a Prism structured request
$conversation->toPrismEmbeddings()  // Get a Prism embeddings request
$conversation->addPrismResponse()   // Add response with automatic metadata
$conversation->streamPrismResponse() // Handle streaming responses
```

## Your Existing Code Still Works

All your current Converse code continues to function:

```php
// These all work exactly as before
$conversation = $user->startConversation(['title' => 'Chat']);
$conversation->addSystemMessage('You are helpful');
$conversation->addUserMessage('Hello');
$messages = $conversation->messages;
$lastMessage = $conversation->lastMessage;
```

## Gradual Adoption

You can migrate at your own pace. Use your existing AI integration for current features while adopting Prism for new ones:

```php
// Old way - still works
$response = $yourAiClient->chat([
    'messages' => $conversation->messages->toArray()
]);
$conversation->addAssistantMessage($response['content']);

// New way - when you're ready
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();
$conversation->addPrismResponse($response->text);
```

## Custom Models

If you've extended the Conversation or Message models, they continue to work:

```php
class User extends Authenticatable
{
    use HasAIConversations;
    
    // Use your custom model
    protected function conversationModel(): string
    {
        return CustomConversation::class;
    }
}
```

## Database

No database changes required. Converse Prism uses the same tables as Converse.

## Next Steps

- Start using [Basic Usage](basic-usage.md) patterns with Prism
- Implement [Streaming](streaming.md) for better user experience
- Explore [Advanced Features](advanced-features.md) like tools and structured outputs 