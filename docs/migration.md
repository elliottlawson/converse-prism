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

## Database

No database changes required. Converse Prism uses the same tables as Converse.

## Next Steps

- Start using [Basic Usage](basic-usage.md) patterns with Prism
- Implement [Streaming](streaming.md) for better user experience
- Explore [Advanced Features](advanced-features.md) like tools and structured outputs 