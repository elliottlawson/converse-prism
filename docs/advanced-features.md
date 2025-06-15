# Advanced Features

This guide covers advanced features including tool/function calling, structured outputs, embeddings, and other powerful capabilities.

## Tool/Function Calling

Converse Prism fully supports Prism's tool calling capabilities, automatically handling the message types for tool calls and results.

### Basic Tool Usage

```php
use EchoLabs\Prism\Enums\ToolChoice;
use EchoLabs\Prism\Tools\Tool;
use Prism\Enums\Provider;

// Define a tool
$weatherTool = Tool::create([
    'name' => 'get_weather',
    'description' => 'Get the current weather for a location',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'location' => [
                'type' => 'string',
                'description' => 'The city and country, e.g. "Paris, France"'
            ],
            'unit' => [
                'type' => 'string',
                'enum' => ['celsius', 'fahrenheit'],
                'description' => 'Temperature unit'
            ]
        ],
        'required' => ['location']
    ]
]);

// Make request with tool
$response = $conversation
    ->addUserMessage('What\'s the weather like in Tokyo?')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withTools([$weatherTool])
    ->withToolChoice(ToolChoice::Auto) // Let the AI decide
    ->asText();

// Check if the AI wants to use a tool
if ($response->toolCalls) {
    // Save the tool call message
    $conversation->addPrismResponse($response->text);
    
    // Execute the tool (your implementation)
    $weatherData = $this->getWeather('Tokyo', 'celsius');
    
    // Add the tool result
    $conversation->addToolResultMessage(json_encode($weatherData));
    
    // Continue the conversation to get final response
    $finalResponse = $conversation
        ->toPrismText()
        ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
        ->asText();
        
    $conversation->addPrismResponse($finalResponse->text);
}
```

### Multiple Tools

```php
$tools = [
    Tool::create([
        'name' => 'search_products',
        'description' => 'Search for products in the catalog',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'max_price' => ['type' => 'number']
            ],
            'required' => ['query']
        ]
    ]),
    
    Tool::create([
        'name' => 'check_inventory',
        'description' => 'Check product inventory levels',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'string']
            ],
            'required' => ['product_id']
        ]
    ]),
    
    Tool::create([
        'name' => 'calculate_shipping',
        'description' => 'Calculate shipping cost',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'string'],
                'destination' => ['type' => 'string'],
                'express' => ['type' => 'boolean']
            ],
            'required' => ['product_id', 'destination']
        ]
    ])
];

$response = $conversation
    ->addUserMessage('Find me a laptop under $1000 and check if it\'s in stock')
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->withTools($tools)
    ->withToolChoice(ToolChoice::Auto)
    ->asText();
```

### Handling Multiple Tool Calls

Some providers support calling multiple tools in one response:

```php
if ($response->toolCalls) {
    $conversation->addPrismResponse($response->text);
    
    // Process each tool call
    $results = [];
    foreach ($response->toolCalls as $toolCall) {
        $result = match($toolCall['function']['name']) {
            'search_products' => $this->searchProducts(
                json_decode($toolCall['function']['arguments'], true)
            ),
            'check_inventory' => $this->checkInventory(
                json_decode($toolCall['function']['arguments'], true)
            ),
            default => ['error' => 'Unknown tool']
        };
        
        $results[] = [
            'tool_call_id' => $toolCall['id'],
            'result' => $result
        ];
    }
    
    // Add all results
    $conversation->addToolResultMessage(json_encode($results));
    
    // Get final response
    $finalResponse = $conversation
        ->toPrismText()
        ->using(Provider::OpenAI, 'gpt-4')
        ->asText();
}
```

### Tool Choice Strategies

```php
// Always use a specific tool
->withToolChoice(ToolChoice::specific('get_weather'))

// Let the AI decide whether to use tools
->withToolChoice(ToolChoice::Auto)

// Prevent tool usage
->withToolChoice(ToolChoice::None)

// Force the AI to use at least one tool
->withToolChoice(ToolChoice::Required)
```

## Structured Output

Generate responses that conform to specific schemas:

### Basic Structured Output

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
        'key_phrases' => [
            'type' => 'array',
            'items' => ['type' => 'string']
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
echo $data['sentiment'];     // "positive"
echo $data['confidence'];    // 0.95
echo $data['summary'];       // "Very positive review expressing satisfaction"

// Store the response
$conversation->addPrismResponse(json_encode($data));
```

### Complex Structured Output

```php
// Define a complex schema for a task management system
$taskSchema = [
    'type' => 'object',
    'properties' => [
        'task' => [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'medium', 'high', 'urgent']
                ],
                'estimated_hours' => ['type' => 'number'],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'subtasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'completed' => ['type' => 'boolean']
                        ]
                    ]
                ]
            ],
            'required' => ['title', 'priority']
        ],
        'reasoning' => ['type' => 'string']
    ],
    'required' => ['task', 'reasoning']
];

$response = $conversation
    ->addSystemMessage('You are a project management assistant')
    ->addUserMessage('Create a task for implementing user authentication')
    ->toPrismStructured()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->withSchema($taskSchema)
    ->asStructured();

// Use the structured data
$taskData = $response->structured;
$task = Task::create($taskData['task']);
```

### Response Enums

For responses with fixed options:

```php
$moodSchema = [
    'type' => 'string',
    'enum' => ['happy', 'sad', 'angry', 'surprised', 'neutral']
];

$response = $conversation
    ->addUserMessage('I just won the lottery!')
    ->toPrismStructured()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withSchema($moodSchema)
    ->asStructured();

$mood = $response->structured; // "happy"
```

## Embeddings

Generate vector embeddings for semantic search, similarity comparison, and more:

### Basic Embedding Generation

```php
// Generate embeddings for text
$response = $conversation
    ->addUserMessage('Laravel is a PHP web framework')
    ->toPrismEmbeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->asEmbeddings();

// Access the embeddings
$embeddings = $response->embeddings; // Array of float vectors

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

### Batch Embeddings

Generate embeddings for multiple texts:

```php
$texts = [
    'Laravel is great for web development',
    'Python is popular for machine learning',
    'JavaScript runs in the browser'
];

// Use Prism directly for batch embeddings
$response = Prism::embeddings()
    ->using(Provider::OpenAI, 'text-embedding-3-small')
    ->withInput($texts)
    ->asEmbeddings();

// Process each embedding
foreach ($response->embeddings as $index => $embedding) {
    // Store or use embeddings
    VectorStore::insert([
        'text' => $texts[$index],
        'embedding' => $embedding,
        'dimensions' => count($embedding)
    ]);
}
```

### Semantic Search Implementation

```php
class SemanticSearch
{
    public function searchConversations(User $user, string $query)
    {
        // Generate query embedding
        $queryEmbedding = Prism::embeddings()
            ->using(Provider::OpenAI, 'text-embedding-3-small')
            ->withInput($query)
            ->asEmbeddings()
            ->embeddings[0];
            
        // Search using vector similarity (example with PostgreSQL pgvector)
        return $user->conversations()
            ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
            ->selectRaw('
                conversations.*,
                messages.content,
                1 - (messages.embedding <=> ?) as similarity
            ', [json_encode($queryEmbedding)])
            ->where('messages.embedding', '!=', null)
            ->orderByDesc('similarity')
            ->limit(10)
            ->get();
    }
}
```

## Image Analysis

For providers that support vision capabilities:

### Single Image Analysis

```php
use EchoLabs\Prism\Messages\UserMessage;
use EchoLabs\Prism\Messages\Message;

// Create a user message with image
$imageMessage = UserMessage::fromArray([
    'content' => [
        [
            'type' => 'text',
            'text' => 'What\'s in this image?'
        ],
        [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg'
            ]
        ]
    ]
]);

// Add to conversation and analyze
$conversation->messages()->create([
    'role' => 'user',
    'content' => json_encode($imageMessage->content),
    'metadata' => ['has_image' => true]
]);

$response = Prism::text()
    ->withMessages($conversation->toPrismMessages())
    ->using(Provider::OpenAI, 'gpt-4o')
    ->asText();

$conversation->addPrismResponse($response->text);
```

### Multiple Images

```php
$content = [
    ['type' => 'text', 'text' => 'Compare these two designs:'],
    [
        'type' => 'image_url',
        'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageData1]
    ],
    [
        'type' => 'image_url', 
        'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageData2]
    ]
];

$conversation->messages()->create([
    'role' => 'user',
    'content' => json_encode($content),
    'metadata' => ['image_count' => 2]
]);

// Continue with analysis...
```

## Response Caching

Implement caching for expensive AI calls:

```php
use Illuminate\Support\Facades\Cache;

class CachedAIService
{
    public function getCachedResponse(Conversation $conversation, string $message)
    {
        // Create a cache key based on conversation context
        $cacheKey = $this->generateCacheKey($conversation, $message);
        
        return Cache::remember($cacheKey, 3600, function () use ($conversation, $message) {
            $response = $conversation
                ->addUserMessage($message)
                ->toPrismText()
                ->using(Provider::OpenAI, 'gpt-4')
                ->asText();
                
            $conversation->addPrismResponse($response->text);
            
            return $response->text;
        });
    }
    
    private function generateCacheKey(Conversation $conversation, string $message): string
    {
        $context = $conversation->messages()
            ->latest()
            ->take(5)
            ->pluck('content')
            ->join('|');
            
        return 'ai_response:' . md5($context . '|' . $message);
    }
}
```

## Token Management

Track and limit token usage:

```php
class TokenManager
{
    public function checkTokenLimit(Conversation $conversation, int $maxTokens = 10000): bool
    {
        $totalTokens = $conversation->messages()
            ->whereNotNull('metadata->tokens')
            ->sum('metadata->tokens');
            
        return $totalTokens < $maxTokens;
    }
    
    public function getTokenUsage(User $user, Carbon $since = null): array
    {
        $query = $user->conversations()
            ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
            ->whereNotNull('messages.metadata->tokens');
            
        if ($since) {
            $query->where('messages.created_at', '>=', $since);
        }
        
        return [
            'total_tokens' => $query->sum('messages.metadata->tokens'),
            'prompt_tokens' => $query->sum('messages.metadata->prompt_tokens'),
            'completion_tokens' => $query->sum('messages.metadata->completion_tokens'),
            'message_count' => $query->count(),
            'estimated_cost' => $this->calculateCost($query->get())
        ];
    }
}
```

## Custom Message Types

Extend the message system for specialized use cases:

```php
// In your Message model
class CustomMessage extends Message
{
    public function toPrismMessage(): mixed
    {
        return match($this->role) {
            'system' => SystemMessage::fromString($this->content),
            'user' => UserMessage::fromString($this->content),
            'assistant' => AssistantMessage::fromString($this->content),
            'tool_call' => AssistantMessage::fromArray([
                'content' => $this->content,
                'tool_calls' => json_decode($this->metadata['tool_calls'] ?? '[]', true)
            ]),
            'tool_result' => ToolResultMessage::fromArray([
                'content' => $this->content,
                'tool_call_id' => $this->metadata['tool_call_id'] ?? null
            ]),
            // Add custom types
            'image' => UserMessage::fromArray([
                'content' => [
                    ['type' => 'text', 'text' => $this->content],
                    ['type' => 'image_url', 'image_url' => ['url' => $this->metadata['image_url']]]
                ]
            ]),
            default => UserMessage::fromString($this->content)
        };
    }
}
```

## Best Practices

### 1. Tool Design
- Keep tool descriptions clear and concise
- Use descriptive parameter names
- Include examples in descriptions when helpful
- Test tools thoroughly before deployment

### 2. Structured Output
- Always validate schemas before using
- Handle potential parsing errors
- Use appropriate data types
- Consider versioning schemas

### 3. Embeddings
- Choose appropriate embedding models for your use case
- Store embeddings efficiently (consider compression)
- Implement proper similarity thresholds
- Cache embeddings when possible

### 4. Performance
- Use streaming for long responses
- Implement request queuing for rate limits
- Cache expensive operations
- Monitor token usage

## Next Steps

- See [Examples](examples.md) for complete implementations
- Review the [API Reference](api-reference.md) for all available methods
- Learn about [Migration](migration-guide.md) from standard Converse 