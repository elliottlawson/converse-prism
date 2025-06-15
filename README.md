# Converse-Prism

[![Tests](https://github.com/elliottlawson/converse-prism/workflows/Tests/badge.svg)](https://github.com/elliottlawson/converse-prism/actions)
[![Latest Stable Version](https://poser.pugx.org/elliottlawson/converse-prism/v)](https://packagist.org/packages/elliottlawson/converse-prism)
[![Total Downloads](https://poser.pugx.org/elliottlawson/converse-prism/downloads)](https://packagist.org/packages/elliottlawson/converse-prism)
[![License](https://poser.pugx.org/elliottlawson/converse-prism/license)](https://packagist.org/packages/elliottlawson/converse-prism)

**Seamless integration between Laravel Converse and Prism PHP for AI conversations.**

Converse-Prism extends [Laravel Converse](https://github.com/elliottlawson/converse) with a fluent API to work directly with [Prism PHP](https://github.com/echolabsdev/prism), supporting both standard and streaming responses.

## 📚 Documentation

**[View the full documentation](https://github.com/elliottlawson/converse-prism/tree/main/docs)** - Comprehensive guides, API reference, and examples.

<!-- Once docs site is live, update to: https://converse-prism.netlify.app -->

## The Magic

The most powerful feature is **automatic message passing**. When you call `toPrismText()`, the package automatically passes all your conversation messages to Prism:

```php
// What you write:
$response = $conversation
    ->addUserMessage('Explain quantum computing')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(500)
    ->asText();

// The conversation history is automatically included!
$conversation->addPrismResponse($response->text);
```

No manual message extraction, no format conversion - it just works.

## Features

- 🔄 **Fluent Interface** - Chain conversation building with Prism API calls
- 🎯 **Automatic Message Passing** - Conversation history automatically sent to AI
- 🌊 **Elegant Streaming** - Built-in support for real-time responses
- 🛠️ **Tool/Function Support** - Full support for AI tool calling
- 📊 **Metadata Extraction** - Automatic token counting and model tracking
- 🔌 **Provider Agnostic** - Works with OpenAI, Anthropic, Google, and more

## Installation

```bash
composer require elliottlawson/converse-prism
```

## Quick Start

Replace the Converse trait with the Converse-Prism trait:

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

// Simple conversation
$response = $user->startConversation()
    ->addSystemMessage('You are a helpful assistant')
    ->addUserMessage('What is Laravel?')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->asText();

$conversation->addPrismResponse($response->text);

// Streaming response
$stream = $conversation->streamPrismResponse();

$response = $conversation
    ->addUserMessage('Write a story')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
        echo $chunk; // Display to user
    });

$stream->complete($response);
```

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- [Laravel Converse](https://github.com/elliottlawson/converse) ^0.1
- [Prism PHP](https://github.com/echolabsdev/prism) ^0.71

## Documentation

- [Getting Started](docs/getting-started.md) - Installation and setup
- [Basic Usage](docs/basic-usage.md) - Core features and patterns
- [Streaming](docs/streaming.md) - Real-time streaming responses
- [Advanced Features](docs/advanced-features.md) - Tools, structured output, embeddings
- [API Reference](docs/api-reference.md) - Complete method documentation
- [Examples](docs/examples.md) - Real-world implementations
- [Migration Guide](docs/migration-guide.md) - Upgrading from Converse

## Why Converse-Prism?

If you're already using Laravel Converse for conversation management and want to:
- Add direct AI integration without writing boilerplate
- Support multiple AI providers with one interface
- Implement streaming responses easily
- Use advanced features like tool calling and structured output

Then Converse-Prism is for you. It's a drop-in enhancement that preserves all your existing Converse functionality while adding powerful Prism integration.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.