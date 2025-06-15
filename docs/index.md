# Converse Prism
## Seamless Prism PHP Integration for Laravel Converse

AI SDKs are great at sending messages, but terrible at having conversations. Converse Prism makes AI conversations flow as naturally as Eloquent makes database queries.

<div class="actions">
  <a href="/installation" class="button primary">Installation</a>
  <a href="https://github.com/elliottlawson/converse-prism" class="button">View on GitHub</a>
</div>

<div class="features">
  <div class="feature">
    <div class="feature-icon">💾</div>
    <h3>Database-Backed AI Conversations</h3>
    <p>Conversations and AI responses are stored in your database, surviving page reloads and server restarts. Query your AI history with Eloquent.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🔌</div>
    <h3>Provider Agnostic</h3>
    <p>Works with OpenAI, Anthropic, Google, or any LLM. Switch providers without changing your code. Your data stays in your database.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">📡</div>
    <h3>Streaming Made Simple</h3>
    <p>Handle real-time AI responses elegantly. Automatic message chunking, progress tracking, and error recovery built-in.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🧠</div>
    <h3>Automatic Context Management</h3>
    <p>Your entire conversation history flows to AI providers automatically. No manual message extraction or formatting—just natural conversation.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🛠️</div>
    <h3>Built-in Function Calling</h3>
    <p>Full support for AI tool calling with automatic message type handling. Tool calls and results are tracked just like any other message.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">📊</div>
    <h3>Laravel Native</h3>
    <p>Built on Laravel Converse, it feels like Laravel code should. Use familiar patterns like $conversation->toPrismText() throughout your app.</p>
  </div>
</div>

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