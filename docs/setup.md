# Setup

After installation, you need to configure your User model and set up your AI provider API keys.

## Configure Your User Model

Replace the standard Converse trait with the Converse Prism trait in your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    
    // Rest of your user model...
}
```

That's it! Your User model is now ready to manage AI conversations.

## Configure AI Provider API Keys

Add your AI provider API keys to your `.env` file:

```bash
# OpenAI
OPENAI_API_KEY=your-openai-api-key

# Anthropic
ANTHROPIC_API_KEY=your-anthropic-api-key

# Google
GEMINI_API_KEY=your-gemini-api-key

# Groq (optional)
GROQ_API_KEY=your-groq-api-key
```

You only need to add API keys for the providers you plan to use.

## Verify Your Setup

To verify everything is working, try creating your first conversation:

```php
use Prism\Enums\Provider;

// Get the authenticated user
$user = auth()->user();

// Start a new conversation
$conversation = $user->startConversation(['title' => 'Test Conversation']);

// Add a message and get AI response
$response = $conversation
    ->addUserMessage('Hello! Can you confirm you\'re working?')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(50)
    ->asText();

// Store the response
$conversation->addPrismResponse($response->text);

echo $response->text; // Should see a greeting from the AI
```

## Troubleshooting

### API Key Issues

Verify your environment variables are set correctly:

```bash
php artisan tinker
>>> env('OPENAI_API_KEY')
```

### Class Not Found

If you get class not found errors after updating your User model:

```bash
composer dump-autoload
```

### Provider Errors

Ensure you're using a valid provider and model combination:

```php
// Valid combinations
->using(Provider::OpenAI, 'gpt-4')
->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
->using(Provider::Google, 'gemini-2.0-flash-exp')
->using(Provider::Groq, 'llama-3.3-70b-versatile')
```

## Next Steps

- If you're migrating from Converse, see [Migration from Converse](migration.md)
- Learn about [Basic Usage](basic-usage.md) patterns
- Explore [Streaming](streaming.md) for real-time responses 