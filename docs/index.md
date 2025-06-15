# Converse Prism Documentation

<div align="center">

**Seamless integration between Converse and Prism PHP for AI conversations**

[![Tests](https://github.com/elliottlawson/converse-prism/workflows/Tests/badge.svg)](https://github.com/elliottlawson/converse-prism/actions)
[![Latest Stable Version](https://poser.pugx.org/elliottlawson/converse-prism/v)](https://packagist.org/packages/elliottlawson/converse-prism)
[![Total Downloads](https://poser.pugx.org/elliottlawson/converse-prism/downloads)](https://packagist.org/packages/elliottlawson/converse-prism)
[![License](https://poser.pugx.org/elliottlawson/converse-prism/license)](https://packagist.org/packages/elliottlawson/converse-prism)

</div>

## Welcome

Converse Prism brings together two powerful Laravel packages: [Converse](https://github.com/elliottlawson/converse) for conversation management and [Prism PHP](https://github.com/echolabsdev/prism) for AI provider integration. 

This integration gives you the best of both worlds:
- **From Converse**: Database-backed conversations, automatic context management, and Laravel-native features
- **From Prism**: A unified API for multiple AI providers, streaming support, and structured outputs
- **From the Integration**: Automatic message format conversion, fluent API design, and seamless streaming

## Quick Links

<div class="grid">

📚 **[Getting Started](getting-started.md)**  
Installation, setup, and your first conversation

🚀 **[Basic Usage](basic-usage.md)**  
Core features and common patterns

🌊 **[Streaming](streaming.md)**  
Real-time streaming responses

🛠️ **[Advanced Features](advanced-features.md)**  
Tools, structured outputs, and more

📖 **[API Reference](api-reference.md)**  
Complete method documentation

💡 **[Examples](examples.md)**  
Real-world implementation patterns

</div>

## Why Converse Prism?

### The Problem

When building AI-powered applications in Laravel, you need to:
1. **Manage conversations** - Store messages, track context, handle user sessions
2. **Integrate with AI providers** - Call APIs, handle responses, manage streaming
3. **Bridge the gap** - Convert between your database models and AI provider formats

Doing this manually means writing boilerplate code for every interaction.

### The Solution

Converse Prism eliminates the boilerplate by providing a fluent interface that connects your conversation models directly to AI providers:

```php
// Without Converse Prism
$messages = $conversation->messages->map(function ($message) {
    return match($message->role) {
        'system' => new SystemMessage($message->content),
        'user' => new UserMessage($message->content),
        'assistant' => new AssistantMessage($message->content),
        // ... handle other roles
    };
})->toArray();

$response = Prism::text()
    ->withMessages($messages)
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(1000)
    ->asText();

$conversation->messages()->create([
    'role' => 'assistant',
    'content' => $response->text,
    'metadata' => [
        'model' => 'claude-3-5-sonnet-latest',
        'tokens' => $response->usage?->total ?? null,
        // ... extract other metadata
    ]
]);

// With Converse Prism
$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(1000)
    ->asText();

$conversation->addPrismResponse($response->text);
```

## Key Features

### 🔄 Automatic Message Conversion
Seamlessly convert between Converse database messages and Prism message objects. No manual mapping required.

### 🎯 Fluent API Design
Chain conversation operations with Prism API calls in a natural, readable way.

### 📊 Metadata Extraction
Automatically extract and store token counts, model information, and other metadata from AI responses.

### 🌊 Elegant Streaming
Handle streaming responses with automatic chunk storage and progress tracking.

### 🛠️ Tool Integration
Full support for function calling / tool use with automatic message type handling.

### 🔌 Provider Agnostic
Works with any AI provider supported by Prism: OpenAI, Anthropic, Google, and more.

## Next Steps

Ready to get started? Head to the [Installation](installation.md) guide to install Converse Prism and create your first AI conversation.

For those already familiar with Converse, check out the [Migration from Converse](migration.md) guide to upgrade your existing code. 