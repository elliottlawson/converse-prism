# Converse Prism

[![Tests](https://github.com/elliottlawson/converse-prism/workflows/Tests/badge.svg)](https://github.com/elliottlawson/converse-prism/actions)
[![Latest Stable Version](https://poser.pugx.org/elliottlawson/converse-prism/v)](https://packagist.org/packages/elliottlawson/converse-prism)
[![Total Downloads](https://poser.pugx.org/elliottlawson/converse-prism/downloads)](https://packagist.org/packages/elliottlawson/converse-prism)
[![License](https://poser.pugx.org/elliottlawson/converse-prism/license)](https://packagist.org/packages/elliottlawson/converse-prism)

Converse Prism seamlessly connects [Converse](https://github.com/elliottlawson/converse) conversations with [Prism PHP](https://github.com/echolabsdev/prism) AI providers. Write `$conversation->toPrismText()` and your entire conversation history flows to any AI provider—no manual message formatting required.

## Installation

```bash
composer require elliottlawson/converse-prism
```

## Quick Start

Update your User model to use the Converse Prism trait:

```php
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
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

// Store the AI's response
$conversation->addPrismResponse($response->text);
```

## Features

- **Automatic Message Passing** - Conversation history automatically flows to AI providers
- **Direct AI Integration** - Works with OpenAI, Anthropic, Google, and any Prism-supported provider
- **Elegant Streaming** - Real-time responses with automatic chunk collection
- **Tool & Function Support** - Full support for AI tool calling
- **Drop-in Enhancement** - All existing Converse code continues working

## Documentation

Full documentation is available at [https://github.com/elliottlawson/converse-prism/tree/main/docs](https://github.com/elliottlawson/converse-prism/tree/main/docs)

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.