<p align="center">
  <img src="converse-icon.png" alt="Converse Prism Logo" width="120">
</p>

<br>

<p align="center">
  <a href="https://github.com/elliottlawson/converse-prism/actions"><img src="https://github.com/elliottlawson/converse-prism/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/elliottlawson/converse-prism"><img src="https://poser.pugx.org/elliottlawson/converse-prism/v" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/elliottlawson/converse-prism"><img src="https://poser.pugx.org/elliottlawson/converse-prism/downloads" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/elliottlawson/converse-prism"><img src="https://poser.pugx.org/elliottlawson/converse-prism/license" alt="License"></a>
</p>

<br>

# Converse Prism - Seamless AI Integration for Laravel Converse

**Managing conversation context is hard. Managing AI provider APIs is harder. Doing both is a nightmare.**

Converse Prism bridges [Laravel Converse](https://github.com/elliottlawson/converse) with [Prism PHP](https://github.com/echolabsdev/prism) to make AI conversations effortless. Write `$conversation->toPrismText()` and your entire conversation history flows to any AI provider—OpenAI, Anthropic, Google, or beyond. No manual message formatting. No provider lock-in. Just conversations that work.

## 📚 Documentation

**[View the full documentation](https://converse-prism.netlify.app)** - Comprehensive guides, API reference, and examples.

## The Magic

Without Converse Prism, you're juggling two complex systems:

```php
// Extract messages from Converse 😓
$messages = [];
foreach ($conversation->messages as $message) {
    $messages[] = [
        'role' => $message->role,
        'content' => $message->content
    ];
}

// Manually configure Prism
$prism = Prism::text()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMessages($messages)
    ->withMaxTokens(500);

// Make the call
$response = $prism->generate();

// Figure out metadata storage...
$conversation->messages()->create([
    'role' => 'assistant',
    'content' => $response->text,
    // What about tokens? Model info? 🤷
]);
```

With Converse Prism, it's seamless:

```php
// Everything flows automatically ✨
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(500)
    ->asText();

// Store response with all metadata
$conversation->addPrismResponse($response->text);
```

That's it. **Your conversation history becomes your AI context. Automatically.**

## Features

- 🔄 **Automatic Message Passing** - Conversation history flows to AI providers without manual formatting
- 🎯 **Direct Prism Integration** - First-class support for all Prism features and providers
- 🌊 **Elegant Streaming** - Real-time responses with automatic chunk collection and storage
- 🛠️ **Tool & Function Support** - Handle complex AI workflows with automatic message type management
- 📊 **Complete Metadata** - Token counts, model info, and response metadata stored automatically
- 🚀 **Drop-in Enhancement** - Works with all existing Converse code, just adds Prism superpowers

## Installation

```bash
composer require elliottlawson/converse-prism
```

The Prism package will be installed automatically. Run the migrations:

```bash
php artisan migrate
```

## Quick Start

Update your User model to use the Converse Prism trait:

```php
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations; // Replaces the base Converse trait
}
```

Start having AI conversations:

```php
use Prism\Enums\Provider;

// Build the conversation context
$conversation = $user->startConversation(['title' => 'My Chat'])
    ->addSystemMessage('You are a helpful assistant')
    ->addUserMessage('Hello! What is Laravel?');

// Make your AI call with automatic message passing
$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(500)
    ->asText();

// Store the AI's response with metadata
$conversation->addPrismResponse($response->text);
```

## Requirements

- PHP 8.2+
- Laravel 11.0+
- Converse ^1.0
- Prism ^0.6

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.