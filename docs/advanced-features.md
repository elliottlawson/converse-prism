# Advanced Features

This guide covers how Converse Prism integrates with Prism's advanced features: tool/function calling, structured outputs, and embeddings.

## Tool/Function Calling

Converse Prism automatically handles the message types for tool calls and results through `addPrismResponse()` and `addToolResultMessage()`.

### Basic Tool Usage

```php
use Prism\Enums\Provider;

// Make request with tools (see Prism docs for tool definition)
$response = $conversation
    ->addUserMessage('What\'s the weather like in Tokyo?')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withTools([$weatherTool])
    ->asText();

// Check if the AI wants to use a tool
if ($response->toolCalls) {
    // Save the tool call message - automatically extracts tool call metadata
    $conversation->addPrismResponse($response);
    
    // Execute your tool and get results
    $weatherData = $this->getWeather('Tokyo', 'celsius');
    
    // Add the tool result - creates a proper tool result message
    $conversation->addToolResultMessage(json_encode($weatherData));
    
    // Continue the conversation to get final response
    $finalResponse = $conversation
        ->toPrismText()
        ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
        ->asText();
        
    $conversation->addPrismResponse($finalResponse);
}
```

### Handling Multiple Tool Calls

When the AI calls multiple tools, `addPrismResponse()` properly stores all tool calls:

```php
if ($response->toolCalls) {
    // This automatically handles multiple tool calls
    $conversation->addPrismResponse($response);
    
    // Process each tool call and collect results
    $results = [];
    foreach ($response->toolCalls as $toolCall) {
        $result = $this->executeTool(
            $toolCall['function']['name'],
            json_decode($toolCall['function']['arguments'], true)
        );
        
        $results[] = [
            'tool_call_id' => $toolCall['id'],
            'result' => $result
        ];
    }
    
    // Add all results at once
    $conversation->addToolResultMessage(json_encode($results));
}
```

## Structured Output

The `toPrismStructured()` method allows you to generate responses that conform to specific schemas:

```php
$schema = [
    'type' => 'object',
    'properties' => [
        'sentiment' => [
            'type' => 'string',
            'enum' => ['positive', 'negative', 'neutral']
        ],
        'confidence' => [
            'type' => 'number',
            'minimum' => 0,
            'maximum' => 1
        ],
        'summary' => [
            'type' => 'string',
            'maxLength' => 200
        ]
    ],
    'required' => ['sentiment', 'confidence', 'summary']
];

$response = $conversation
    ->addUserMessage('Analyze this review: "The product exceeded my expectations!"')
    ->toPrismStructured()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withSchema($schema)
    ->asStructured();

// Access the structured data
$data = $response->structured;

// Store the response - automatically serializes structured data
$conversation->addPrismResponse($response);
```

## Embeddings

The `toPrismEmbeddings()` method generates embeddings from the last user message:

```php
// Generate embeddings for the last user message
$response = $conversation
    ->addUserMessage('Laravel is a PHP web framework')
    ->toPrismEmbeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->asEmbeddings();

// Access the embeddings
$embeddings = $response->embeddings;

// Store embeddings with metadata
$conversation->messages()->latest()->first()->update([
    'metadata' => array_merge(
        $conversation->lastMessage->metadata ?? [],
        [
            'embeddings' => $embeddings,
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => count($embeddings[0] ?? [])
        ]
    )
]);
```

### Using Embeddings

Once stored, you can use embeddings for similarity search:

```php
// Example: Find similar conversations
public function findSimilarMessages(Conversation $conversation, Message $message)
{
    if (!isset($message->metadata['embeddings'])) {
        return collect();
    }
    
    // Use your vector database to find similar messages
    return $conversation->messages()
        ->whereNotNull('metadata->embeddings')
        ->where('id', '!=', $message->id)
        ->get()
        ->sortByDesc(function ($otherMessage) use ($message) {
            // Calculate cosine similarity
            return $this->cosineSimilarity(
                $message->metadata['embeddings'],
                $otherMessage->metadata['embeddings']
            );
        })
        ->take(5);
}
```

## Message Type Handling

Converse Prism automatically handles different message types through its methods:

- **Tool Calls**: `addPrismResponse()` detects and stores tool call messages with proper metadata
- **Tool Results**: `addToolResultMessage()` creates properly formatted tool result messages
- **Structured Data**: `addPrismResponse()` automatically serializes structured response data
- **Regular Messages**: Standard text responses are stored with all metadata (tokens, model, etc.)

This automatic handling ensures your conversation history accurately reflects all interaction types without manual message formatting.

## Next Steps

- See [Examples](examples.md) for complete implementations
- Review the [API Reference](/api/) for all available methods
- Learn about [Streaming](streaming.md) for real-time responses 