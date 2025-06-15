---
layout: home

hero:
  name: "Converse Prism"
  text: "Seamless Prism PHP Integration for Laravel Converse"
  tagline: AI SDKs are great at sending messages, but terrible at having conversations. Converse Prism makes AI conversations flow as naturally as Eloquent makes database queries.
  actions:
    - theme: brand
      text: Installation
      link: /installation
    - theme: alt
      text: View on GitHub
      link: https://github.com/elliottlawson/converse-prism

features:
  - icon: 💾
    title: Database-Backed AI Conversations
    details: Conversations and AI responses are stored in your database, surviving page reloads and server restarts. Query your AI history with Eloquent.
    
  - icon: 🔌
    title: Provider Agnostic
    details: Works with OpenAI, Anthropic, Google, or any LLM. Switch providers without changing your code. Your data stays in your database.
    
  - icon: 📡
    title: Streaming Made Simple
    details: Handle real-time AI responses elegantly. Automatic message chunking, progress tracking, and error recovery built-in.
    
  - icon: 🧠
    title: Automatic Context Management
    details: Your entire conversation history flows to AI providers automatically. No manual message extraction or formatting—just natural conversation.
    
  - icon: 🛠️
    title: Built-in Function Calling
    details: Full support for AI tool calling with automatic message type handling. Tool calls and results are tracked just like any other message.
    
  - icon: 📊
    title: Laravel Native
    details: Built on Laravel Converse, it feels like Laravel code should. Use familiar patterns like $conversation->toPrismText() throughout your app.

---

## Quick Setup

Install the package via Composer:

```bash
composer require elliottlawson/converse-prism
```

Update your User model trait:

```php
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Model
{
    use HasAIConversations;
}
```

Start a conversation with AI:

```php
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