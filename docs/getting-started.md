# Getting Started

This guide will walk you through installing Converse-Prism and creating your first AI conversation.

## Requirements

Before installing Converse-Prism, make sure your project meets these requirements:

- PHP 8.2 or higher
- Laravel 11.0 or 12.0
- [Laravel Converse](https://github.com/elliottlawson/converse) ^0.1
- [Prism PHP](https://github.com/echolabsdev/prism) ^0.71

## Installation

### Step 1: Install via Composer

```bash
composer require elliottlawson/converse-prism
```

This will automatically pull in the required dependencies including Laravel Converse.

### Step 2: Run Migrations

If you haven't already installed Laravel Converse, run the migrations to create the conversation tables:

```bash
php artisan migrate
```

This creates two tables:
- `conversations` - Stores conversation metadata
- `messages` - Stores individual messages

### Step 3: Update Your User Model

Replace the standard Converse trait with the Converse-Prism trait in your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
// Replace the original trait
// use ElliottLawson\Converse\Traits\HasAIConversations;
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    
    // Rest of your user model...
}
```

That's it! All your existing Converse functionality continues to work, plus you now have Prism integration.

## Configuration

### Prism Configuration

Make sure you have configured Prism with your AI provider credentials. If you haven't already, publish the Prism config:

```bash
php artisan vendor:publish --tag=prism-config
```

Then add your API keys to your `.env` file:

```env
# OpenAI
OPENAI_API_KEY=your-openai-api-key

# Anthropic
ANTHROPIC_API_KEY=your-anthropic-api-key

# Google
GEMINI_API_KEY=your-gemini-api-key
```

### Converse Configuration (Optional)

If you want to customize Converse settings, publish the config:

```bash
php artisan vendor:publish --tag=converse-config
```

## Your First Conversation

Let's create a simple AI conversation:

```php
use Prism\Enums\Provider;

// Get the authenticated user
$user = auth()->user();

// Start a new conversation
$conversation = $user->startConversation(['title' => 'My First AI Chat']);

// Add messages and get AI response
$response = $conversation
    ->addSystemMessage('You are a helpful assistant')
    ->addUserMessage('Hello! What can you help me with?')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(150)
    ->asText();

// Store the AI's response
$conversation->addPrismResponse($response->text);

// The conversation is automatically saved to the database!
```

## Understanding the Flow

Let's break down what happens in the example above:

1. **Start a conversation** - Creates a new conversation record in the database
2. **Add messages** - Each message is saved to the database with the appropriate role
3. **Convert to Prism** - `toPrismText()` automatically formats all messages for the AI provider
4. **Configure the request** - Use Prism's fluent API to set provider, model, and parameters
5. **Get the response** - Send the request and receive the AI's response
6. **Store the response** - Save the AI's response back to the conversation

## Next Steps

Now that you have Converse-Prism installed and working, you can:

- Learn about [Basic Usage](basic-usage.md) patterns
- Implement [Streaming](streaming.md) for real-time responses
- Explore [Advanced Features](advanced-features.md) like tools and structured outputs
- Check the [API Reference](api-reference.md) for all available methods

## Troubleshooting

### Common Issues

**Class not found errors**
```bash
composer dump-autoload
```

**Migration errors**
Make sure you've run the Converse migrations:
```bash
php artisan migrate:status
```

**API key errors**
Verify your environment variables are set correctly:
```bash
php artisan tinker
>>> env('OPENAI_API_KEY')
```

**Provider errors**
Ensure you're using a valid provider and model combination:
```php
// Valid
->using(Provider::OpenAI, 'gpt-4')
->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')

// Invalid - wrong model for provider
->using(Provider::OpenAI, 'claude-3-5-sonnet-latest') // ❌
```

Need more help? Check out the [Examples](examples.md) or open an issue on [GitHub](https://github.com/elliottlawson/converse-prism). 