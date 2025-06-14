# Laravel Converse-Prism Integration

Seamless integration between Laravel Converse and Prism PHP for AI conversations. This package extends Laravel Converse with a fluent API to work directly with Prism, supporting both standard and streaming responses.

## Installation

```bash
composer require elliottlawson/converse-prism
```

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- [Laravel Converse](https://github.com/elliottlawson/converse) ^0.1
- [Prism PHP](https://github.com/echolabsdev/prism) ^0.71

## Features

- **Fluent Interface**: Chain conversation building with Prism API calls
- **Direct Prism Integration**: Get pre-configured Prism request objects
- **Automatic Format Translation**: Seamlessly convert between Converse and Prism message formats
- **Streaming Support**: Elegant handling of Prism streaming responses
- **Metadata Preservation**: Automatically extracts and stores token counts, models, etc.
- **Type Safety**: Full return type declarations and proper handling of all Prism response types
- **Backwards Compatible**: All existing Converse functionality continues to work

## Setup

Replace the Converse trait with the ConversePrism trait in your User model:

```php
// app/Models/User.php
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    
    // Rest of your user model...
}
```

That's it! All your existing Converse code continues to work, plus you get Prism superpowers.

## Usage

### Fluent Conversation Building with Prism

The most powerful feature is the ability to fluently build conversations and get Prism request objects:

```php
use Prism\Enums\Provider;

// Build and send in one fluent chain
$response = $user->startConversation(['title' => 'Code Review'])
    ->addSystemMessage('You are a senior Laravel developer providing code reviews')
    ->addUserMessage('Review this controller method for best practices')
    ->addUserMessage($codeSnippet)
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withMaxTokens(1000)
    ->withTemperature(0.2)
    ->generate();

// Add the response back to the conversation
$conversation->addPrismResponse($response);
```

### Direct Prism Facade Integration

The package provides three methods that return configured Prism request objects:

#### Text Generation
```php
$conversation = $user->conversations()->find($id);

// Get a PendingTextRequest with messages pre-populated
$request = $conversation
    ->addUserMessage('Explain quantum computing')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withMaxTokens(500);

// You have full access to all Prism methods
$response = $request
    ->withTemperature(0.7)
    ->withTopP(0.9)
    ->withPresencePenalty(0.1)
    ->generate();
```

#### Structured Output
```php
$productSchema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'price' => ['type' => 'number'],
        'inStock' => ['type' => 'boolean']
    ]
];

$response = $conversation
    ->addSystemMessage('Extract product information from user messages')
    ->addUserMessage('The new iPhone 15 Pro costs $999 and is available now')
    ->toPrismStructured()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withSchema($productSchema)
    ->generate();

// Save the structured response
$conversation->addPrismResponse($response);
```

#### Embeddings
```php
// Generate embeddings for the last user message
$embeddings = $conversation
    ->addUserMessage('What is the meaning of life?')
    ->toPrismEmbeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->generate();
```

### Message Format Conversion

The package automatically converts between Converse and Prism message formats:

```php
// Get all messages as Prism message objects
$prismMessages = $conversation->toPrismMessages();
// Returns array of typed Prism message objects:
// [
//     SystemMessage { content: "You are helpful" },
//     UserMessage { content: "Hello" },
//     AssistantMessage { content: "Hi there!" }
// ]

// Convert a single message
$message = $conversation->messages()->latest()->first();
$prismMessage = $message->toPrismMessage();
// Returns: UserMessage { content: "Hello" }
```

### Streaming Responses

Handle streaming responses elegantly with automatic chunk storage:

```php
$stream = $conversation->streamPrismResponse([
    'model' => 'claude-3-5-sonnet-latest',
    'provider' => 'anthropic',
]);

$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        $stream->append($chunk);
        
        // Optionally broadcast to frontend
        broadcast(new StreamUpdate($stream->getMessage(), $chunk));
    });

// Complete the stream
$message = $stream->complete($response);
```

### Tool/Function Calling

The package correctly handles Prism's tool calls and results:

```php
$weatherTool = Tool::create([
    'name' => 'get_weather',
    'description' => 'Get current weather for a location',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'location' => ['type' => 'string', 'description' => 'City name'],
            'unit' => ['type' => 'string', 'enum' => ['celsius', 'fahrenheit']]
        ],
        'required' => ['location']
    ]
]);

// Make request with tools
$response = $conversation
    ->addUserMessage('What\'s the weather in Paris?')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withTools([$weatherTool])
    ->generate();

// Automatically detects and saves tool calls
$conversation->addPrismResponse($response);
// Creates a tool_call message if response contains toolCalls

// Add tool result and continue
$conversation->addToolResultMessage(json_encode([
    'temperature' => 22,
    'condition' => 'sunny',
    'unit' => 'celsius'
]));

// Continue the conversation
$finalResponse = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->generate();

$conversation->addPrismResponse($finalResponse);
// "The weather in Paris is currently sunny with a temperature of 22°C."
```

### Complete Example: Building a Chat Interface

```php
namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Prism\Enums\Provider;

class ChatController extends Controller
{
    public function store(Request $request)
    {
        $conversation = auth()->user()->startConversation([
            'title' => $request->input('title', 'New Chat'),
            'metadata' => ['source' => 'web']
        ]);
        
        // Set up the conversation context
        $response = $conversation
            ->addSystemMessage('You are a helpful AI assistant')
            ->addUserMessage($request->input('message'))
            ->toPrismText()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMaxTokens(1000)
            ->generate();
            
        $conversation->addPrismResponse($response);
        
        return response()->json([
            'conversation' => $conversation->fresh()->load('messages'),
            'message' => $conversation->lastMessage
        ]);
    }
    
    public function update(Request $request, Conversation $conversation)
    {
        // Ensure user owns the conversation
        $this->authorize('update', $conversation);
        
        // Continue the conversation
        if ($request->boolean('stream')) {
            return $this->streamResponse($request, $conversation);
        }
        
        $response = $conversation
            ->addUserMessage($request->input('message'))
            ->toPrismText()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMaxTokens(1000)
            ->generate();
            
        $conversation->addPrismResponse($response, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return response()->json([
            'message' => $conversation->lastMessage
        ]);
    }
    
    protected function streamResponse(Request $request, Conversation $conversation)
    {
        $stream = $conversation->streamPrismResponse();
        
        return response()->stream(function () use ($request, $conversation, $stream) {
            try {
                $response = $conversation
                    ->addUserMessage($request->input('message'))
                    ->toPrismText()
                    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
                    ->stream(function ($chunk) use ($stream) {
                        $stream->append($chunk);
                        echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                        ob_flush();
                        flush();
                    });
                    
                $message = $stream->complete($response);
                echo "data: " . json_encode(['done' => true, 'message' => $message]) . "\n\n";
                
            } catch (\Exception $e) {
                $stream->fail($e->getMessage());
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
```

## API Reference

### Conversation Methods

```php
// Convert messages to Prism format
$messages = $conversation->toPrismMessages(); // Returns array of Prism message objects

// Get pre-configured Prism request objects
$textRequest = $conversation->toPrismText();           // Returns PendingTextRequest
$structuredRequest = $conversation->toPrismStructured(); // Returns PendingStructuredRequest  
$embeddingsRequest = $conversation->toPrismEmbeddings(); // Returns PendingEmbeddingRequest

// Add Prism responses
$conversation->addPrismResponse($response, $metadata = []); // Returns self for chaining

// Handle streaming
$stream = $conversation->streamPrismResponse($metadata = []); // Returns PrismStream
```

### Message Methods

```php
// Convert single message to Prism format
$prismMessage = $message->toPrismMessage(); // Returns PrismMessage
```

### PrismStream Methods

```php
$stream->append($chunk);                    // Append a chunk
$stream->complete($prismResponse = null);   // Complete streaming
$stream->fail($error, $errorMetadata = []); // Handle failure
$stream->getMessage();                      // Get underlying message
```

## Metadata Extraction

The package automatically extracts and stores metadata from Prism responses:

- **Token Usage**: `prompt_tokens`, `completion_tokens`, `tokens` (total)
- **Model Information**: `model`, `provider_request_id`
- **Response Details**: `finish_reason`, `steps` (for multi-step)
- **Streaming Info**: `stream_chunks`, `stream_duration`

```php
$conversation->addPrismResponse($response, [
    'custom_field' => 'value' // Your metadata is merged with extracted data
]);
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.