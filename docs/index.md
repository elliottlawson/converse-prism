# Converse Prism
## AI Integration for Converse

Converse Prism seamlessly connects [Converse](https://github.com/elliottlawson/converse) conversations with [Prism PHP](https://github.com/echolabsdev/prism) AI providers. Write `$conversation->toPrismText()` and your entire conversation history flows to any AI provider—no manual message formatting required.

<div class="actions">
  <a href="/setup" class="button primary">Setup</a>
  <a href="https://github.com/elliottlawson/converse-prism" class="button">View on GitHub</a>
</div>

<div class="features">
  <div class="feature">
    <div class="feature-icon">🔄</div>
    <h3>Automatic Message Passing</h3>
    <p>Conversation history automatically flows to AI providers. No manual message extraction or formatting—just call toPrismText() and go.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🎯</div>
    <h3>Direct AI Integration</h3>
    <p>Works with OpenAI, Anthropic, Google, Groq, and any Prism-supported provider. Switch providers without changing your code.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🌊</div>
    <h3>Elegant Streaming</h3>
    <p>Handle real-time AI responses elegantly. Automatic chunk collection, progress tracking, and error recovery built-in.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🛠️</div>
    <h3>Tool & Function Support</h3>
    <p>Full support for AI tool calling. Converse Prism automatically handles tool call and result message types.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">📊</div>
    <h3>Metadata Extraction</h3>
    <p>Automatically extracts and stores token counts, model info, and response metadata. Track usage without extra code.</p>
  </div>

  <div class="feature">
    <div class="feature-icon">🚀</div>
    <h3>Drop-in Enhancement</h3>
    <p>Just change one trait in your User model. All existing Converse code continues working, plus you get Prism superpowers.</p>
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