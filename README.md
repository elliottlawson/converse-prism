# Laravel Converse-Prism Integration Package

Seamless integration between Laravel Converse and Prism PHP for AI conversations. This package provides a fluent API to convert conversation messages to Prism format and save Prism responses back to Converse, supporting both standard and streaming responses.

## Installation

```bash
composer require elliottlawson/converse-prism echolabsdev/prism
```

This will install the integration package along with Laravel Converse (automatically included as a dependency). You need to manually install Prism since it's required for the integration to work.

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- elliottlawson/converse ^0.1.1
- echolabsdev/prism ^0.71

## Features

- **Drop-in Replacement**: Just swap the trait in your User model - no other changes needed
- **Fluid API**: Natural Laravel-style methods on Conversation and Message models
- **Automatic Format Translation**: Converts between Converse and Prism message formats
- **Streaming Support**: Elegant handling of Prism streaming responses
- **Metadata Preservation**: Automatically extracts and stores token counts, models, etc.
- **Type Safety**: Handles all Prism response types correctly
- **Backwards Compatible**: All existing Converse functionality continues to work

## Setup

Add the HasAIConversations trait to your User model:

```php
// app/Models/User.php
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    
    // Rest of your user model...
}
```

### Upgrading from Converse

If you're already using Laravel Converse, just change the trait namespace from `ElliottLawson\Converse\Traits\HasAIConversations` to `ElliottLawson\ConversePrism\Concerns\HasAIConversations`. That's it!

## Usage

### Basic Example

```php
use Prism\Prism;
use Prism\Enums\Provider;

// Create a conversation as usual
$conversation = $user->startConversation(['title' => 'Chat']);

// Add a user message
$conversation->addUserMessage('What is the weather in Paris?');

// Convert messages to Prism format and get AI response
$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMessages($conversation->toPrism())
    ->generate();

// Save the response with automatic metadata extraction
$conversation->addPrismResponse($response);
```

### Streaming Responses

```php
// Start streaming
$stream = $conversation->streamPrismResponse([
    'model' => 'claude-3-5-sonnet-latest',
    'provider' => 'anthropic',
]);

// Stream with Prism
$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMessages($conversation->toPrism())
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
        
        // Optionally broadcast to frontend
        broadcast(new ChunkReceived($stream->getMessage(), $chunk));
    });

// Complete the stream
$message = $stream->complete($response);
```

### Type-Safe Message Conversion

This package automatically converts Converse messages to Prism's typed message objects:

```php
// What you get back from toPrism()
$prismMessages = $conversation->toPrism();
// Returns: [
//     UserMessage { content: "What's the weather?" },
//     AssistantMessage { content: "", toolCalls: [...] },
//     ToolResultMessage { toolResults: [...] },
//     AssistantMessage { content: "It's sunny and 72°F" }
// ]
```

#### Message Type Mapping

| Converse Role | Prism Message Type | Properties |
|--------------|-------------------|-----------|
| `User` | `UserMessage` | `content: string` |
| `Assistant` | `AssistantMessage` | `content: string`, `toolCalls: array` |
| `System` | `SystemMessage` | `content: string` |
| `ToolCall` | `AssistantMessage` | `content: ""`, `toolCalls: ToolCall[]` |
| `ToolResult` | `ToolResultMessage` | `toolResults: ToolResult[]` |

#### Real Example

```php
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;

// Build a conversation
$conversation->addUserMessage('Calculate 15% tip on $42.50');
$conversation->addAssistantMessage('A 15% tip on $42.50 would be $6.38');

// Convert to Prism format
$messages = $conversation->toPrism();

// You get actual Prism message objects!
$messages[0] // UserMessage { content: "Calculate 15% tip on $42.50" }
$messages[1] // AssistantMessage { content: "A 15% tip on $42.50 would be $6.38" }

// Use directly with Prism - no array building needed!
$response = Prism::text()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMessages($messages) // Prism accepts these typed objects
    ->generate();
```

### Handling Tool Calls

```php
// Prism response with tool calls
$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMessages($conversation->toPrism())
    ->withTools([$weatherTool])
    ->generate();

// Automatically saves as tool call message
$message = $conversation->addPrismResponse($response);
// If response has toolCalls, creates a ToolCall message
// If response has toolResults, creates a ToolResult message
```

### Error Handling with Streaming

```php
$stream = $conversation->streamPrismResponse();

try {
    Prism::text()
        ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
        ->withMessages($conversation->toPrism())
        ->stream(function ($chunk) use ($stream) {
            $stream->append($chunk);
        });
        
    $message = $stream->complete($response);
} catch (\Exception $e) {
    $message = $stream->fail($e->getMessage(), [
        'error_type' => get_class($e),
        'provider' => 'anthropic',
    ]);
}
```

### Complete Example with Error Handling

```php
use App\Models\User;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

class ChatController extends Controller
{
    public function sendMessage(Request $request, Conversation $conversation)
    {
        // Add user message
        $conversation->addUserMessage($request->input('message'));
        
        // Convert history for Prism
        $messages = $conversation->toPrism();
        
        try {
            if ($request->input('stream', false)) {
                // Streaming response
                $stream = $conversation->streamPrismResponse([
                    'user_id' => auth()->id(),
                    'ip' => $request->ip(),
                ]);
                
                $response = Prism::text()
                    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
                    ->withMessages($messages)
                    ->stream(function ($chunk) use ($stream) {
                        $stream->append($chunk);
                    });
                    
                $message = $stream->complete($response);
            } else {
                // Standard response
                $response = Prism::text()
                    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
                    ->withMessages($messages)
                    ->generate();
                    
                $message = $conversation->addPrismResponse($response, [
                    'user_id' => auth()->id(),
                    'ip' => $request->ip(),
                ]);
            }
            
            return response()->json([
                'message' => $message,
                'conversation_id' => $conversation->uuid,
            ]);
            
        } catch (\Exception $e) {
            // Handle errors
            if (isset($stream)) {
                $stream->fail($e->getMessage());
            }
            
            return response()->json([
                'error' => 'Failed to generate response',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
```

## Available Methods

### On Conversation Model

- `toPrism()` - Convert all completed messages to Prism format
- `addPrismResponse($response, $metadata = [])` - Add a Prism response as a message
- `streamPrismResponse($metadata = [])` - Start a streaming response

### On Message Model

- `toPrism()` - Convert a single message to Prism format

### PrismStream Methods

- `append($chunk)` - Append a chunk to the streaming message
- `complete($prismResponse = null)` - Complete the streaming response
- `fail($error, $errorMetadata = [])` - Fail the streaming response
- `getMessage()` - Get the underlying message instance

## Metadata Extraction

The package automatically extracts and stores the following metadata from Prism responses:

- Token usage (total, prompt, completion)
- Model information
- Provider request ID
- Finish reason
- Number of steps (for multi-step responses)
- Streaming metadata (chunks, duration)

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.