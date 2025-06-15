# Examples

Real-world examples demonstrating common use cases and patterns.

## Basic Chat Application

### Simple Chat Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Prism\Enums\Provider;

class ChatController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000'
        ]);
        
        $conversation = auth()->user()->startConversation([
            'title' => substr($request->message, 0, 50) . '...'
        ]);
        
        $response = $conversation
            ->addSystemMessage('You are a helpful assistant')
            ->addUserMessage($request->message)
            ->toPrismText()
            ->using(Provider::OpenAI, 'gpt-4')
            ->withMaxTokens(1000)
            ->asText();
            
        $conversation->addPrismResponse($response->text);
        
        return response()->json([
            'conversation_id' => $conversation->id,
            'response' => $response->text
        ]);
    }
    
    public function update(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);
        
        $response = $conversation
            ->addUserMessage($request->input('message'))
            ->toPrismText()
            ->using(Provider::OpenAI, 'gpt-4')
            ->asText();
            
        $conversation->addPrismResponse($response->text);
        
        return response()->json([
            'response' => $response->text
        ]);
    }
}
```

## Streaming Chat with SSE

### Streaming Controller

```php
public function stream(Request $request, Conversation $conversation)
{
    $this->authorize('update', $conversation);
    
    return response()->stream(function () use ($request, $conversation) {
        $stream = $conversation->streamPrismResponse();
        
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
            echo "data: " . json_encode(['done' => true]) . "\n\n";
            
        } catch (\Exception $e) {
            $stream->fail($e->getMessage());
            echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

### Frontend JavaScript

```javascript
const eventSource = new EventSource(`/api/chat/${conversationId}/stream`);
const messageDiv = document.getElementById('response');

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    if (data.chunk) {
        messageDiv.textContent += data.chunk;
    } else if (data.done) {
        eventSource.close();
        console.log('Stream complete');
    } else if (data.error) {
        eventSource.close();
        console.error('Error:', data.error);
    }
};
```

## Tool/Function Calling

### Weather Bot Example

```php
use EchoLabs\Prism\Tools\Tool;
use EchoLabs\Prism\Enums\ToolChoice;

class WeatherBot
{
    protected $weatherTool;
    
    public function __construct()
    {
        $this->weatherTool = Tool::create([
            'name' => 'get_weather',
            'description' => 'Get current weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name'
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit']
                    ]
                ],
                'required' => ['location']
            ]
        ]);
    }
    
    public function chat($conversation, string $message)
    {
        $response = $conversation
            ->addUserMessage($message)
            ->toPrismText()
            ->using(Provider::OpenAI, 'gpt-4')
            ->withTools([$this->weatherTool])
            ->withToolChoice(ToolChoice::Auto)
            ->asText();
        
        if ($response->toolCalls) {
            $conversation->addPrismResponse($response->text);
            
            // Execute tool call
            $toolCall = $response->toolCalls[0];
            $args = json_decode($toolCall['function']['arguments'], true);
            $weather = $this->getWeather($args['location'], $args['unit'] ?? 'celsius');
            
            // Add result and get final response
            $conversation->addToolResultMessage(json_encode($weather));
            
            $finalResponse = $conversation
                ->toPrismText()
                ->using(Provider::OpenAI, 'gpt-4')
                ->asText();
                
            $conversation->addPrismResponse($finalResponse->text);
            
            return $finalResponse->text;
        }
        
        $conversation->addPrismResponse($response->text);
        return $response->text;
    }
    
    protected function getWeather($location, $unit)
    {
        // Your weather API integration
        return [
            'location' => $location,
            'temperature' => 22,
            'unit' => $unit,
            'condition' => 'sunny'
        ];
    }
}
```

## Structured Output

### Form Data Extraction

```php
class FormExtractor
{
    public function extractFormData($conversation, string $userInput)
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => [
                    'type' => 'string',
                    'format' => 'email'
                ],
                'phone' => ['type' => 'string'],
                'reason' => [
                    'type' => 'string',
                    'enum' => ['support', 'sales', 'billing', 'other']
                ],
                'message' => ['type' => 'string'],
                'urgency' => [
                    'type' => 'string',
                    'enum' => ['low', 'medium', 'high']
                ]
            ],
            'required' => ['name', 'email', 'reason', 'message']
        ];
        
        $response = $conversation
            ->addSystemMessage('Extract contact form data from user messages')
            ->addUserMessage($userInput)
            ->toPrismStructured()
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withSchema($schema)
            ->asStructured();
        
        $data = $response->structured;
        $conversation->addPrismResponse(json_encode($data));
        
        return $data;
    }
}
```

## Document Q&A with Context

### Simple RAG Implementation

```php
class DocumentQA
{
    public function answerQuestion(User $user, string $question, array $documents)
    {
        $conversation = $user->startConversation([
            'title' => 'Q&A: ' . substr($question, 0, 40)
        ]);
        
        // Build context from documents
        $context = collect($documents)
            ->map(fn($doc) => "Document: {$doc->title}\n{$doc->content}")
            ->join("\n\n---\n\n");
        
        $response = $conversation
            ->addSystemMessage(
                'Answer questions based only on the provided documents. ' .
                'If the answer is not in the documents, say so.'
            )
            ->addUserMessage(
                "Documents:\n{$context}\n\n" .
                "Question: {$question}"
            )
            ->toPrismText()
            ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->withMaxTokens(500)
            ->asText();
            
        $conversation->addPrismResponse($response->text, [
            'documents_used' => count($documents)
        ]);
        
        return $response->text;
    }
}
```

## Multi-Model Workflow

### Content Generation Pipeline

```php
class ContentPipeline
{
    public function generateArticle(User $user, string $topic)
    {
        $conversation = $user->startConversation([
            'title' => 'Article: ' . $topic
        ]);
        
        // Step 1: Generate outline with GPT-4
        $outlineSchema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'heading' => ['type' => 'string'],
                            'points' => [
                                'type' => 'array',
                                'items' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $outline = $conversation
            ->addSystemMessage('You are an expert content strategist')
            ->addUserMessage("Create an article outline about: {$topic}")
            ->toPrismStructured()
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withSchema($outlineSchema)
            ->asStructured();
            
        $conversation->addPrismResponse(json_encode($outline->structured));
        
        // Step 2: Write content with Claude
        $content = [];
        foreach ($outline->structured['sections'] as $section) {
            $sectionContent = $conversation
                ->addUserMessage(
                    "Write the section '{$section['heading']}' " .
                    "covering: " . implode(', ', $section['points'])
                )
                ->toPrismText()
                ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
                ->withMaxTokens(800)
                ->asText();
                
            $conversation->addPrismResponse($sectionContent->text);
            $content[] = $sectionContent->text;
        }
        
        return [
            'title' => $outline->structured['title'],
            'content' => implode("\n\n", $content),
            'conversation' => $conversation
        ];
    }
}
```

## Error Handling Pattern

```php
class RobustChatService
{
    public function chat($conversation, string $message)
    {
        $maxRetries = 3;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                $response = $conversation
                    ->addUserMessage($message)
                    ->toPrismText()
                    ->using(Provider::OpenAI, 'gpt-4')
                    ->withMaxTokens(500)
                    ->asText();
                    
                $conversation->addPrismResponse($response->text);
                
                return [
                    'success' => true,
                    'response' => $response->text
                ];
                
            } catch (\EchoLabs\Prism\Exceptions\PrismException $e) {
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    // Log error
                    Log::error('Prism API error', [
                        'error' => $e->getMessage(),
                        'conversation_id' => $conversation->id
                    ]);
                    
                    // Save error message
                    $conversation->addAssistantMessage(
                        'I apologize, but I\'m having trouble responding right now.',
                        ['error' => $e->getMessage()]
                    );
                    
                    return [
                        'success' => false,
                        'error' => 'Service temporarily unavailable'
                    ];
                }
                
                // Wait before retry
                sleep($attempt * 2);
            }
        }
    }
}
```

## Token Usage Tracking

```php
class UsageTracker
{
    public function getUsageStats(User $user, $days = 30)
    {
        $since = now()->subDays($days);
        
        $stats = $user->conversations()
            ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
            ->where('messages.created_at', '>=', $since)
            ->whereNotNull('messages.metadata->tokens')
            ->selectRaw('
                COUNT(DISTINCT conversations.id) as conversation_count,
                COUNT(messages.id) as message_count,
                SUM(JSON_EXTRACT(messages.metadata, "$.tokens")) as total_tokens,
                SUM(JSON_EXTRACT(messages.metadata, "$.prompt_tokens")) as prompt_tokens,
                SUM(JSON_EXTRACT(messages.metadata, "$.completion_tokens")) as completion_tokens,
                JSON_EXTRACT(messages.metadata, "$.model") as model
            ')
            ->groupBy('model')
            ->get();
            
        return $stats->map(function ($stat) {
            $costPerToken = $this->getCostPerToken($stat->model);
            
            return [
                'model' => $stat->model,
                'conversations' => $stat->conversation_count,
                'messages' => $stat->message_count,
                'tokens' => [
                    'total' => $stat->total_tokens,
                    'prompt' => $stat->prompt_tokens,
                    'completion' => $stat->completion_tokens
                ],
                'estimated_cost' => [
                    'prompt' => $stat->prompt_tokens * $costPerToken['prompt'],
                    'completion' => $stat->completion_tokens * $costPerToken['completion'],
                    'total' => ($stat->prompt_tokens * $costPerToken['prompt']) + 
                              ($stat->completion_tokens * $costPerToken['completion'])
                ]
            ];
        });
    }
    
    protected function getCostPerToken($model)
    {
        // Example pricing (check current provider pricing)
        return match($model) {
            'gpt-4' => ['prompt' => 0.00003, 'completion' => 0.00006],
            'gpt-4o' => ['prompt' => 0.000005, 'completion' => 0.000015],
            'claude-3-5-sonnet-latest' => ['prompt' => 0.000003, 'completion' => 0.000015],
            default => ['prompt' => 0.000001, 'completion' => 0.000002]
        };
    }
}
```

## Best Practices

1. **Always validate inputs** before sending to AI
2. **Handle errors gracefully** with try-catch blocks
3. **Use streaming** for better user experience
4. **Monitor token usage** to control costs
5. **Cache responses** when appropriate
6. **Set appropriate timeouts** for long-running requests
7. **Use the right model** for each task
8. **Store metadata** for analytics and debugging
9. **Implement rate limiting** to prevent abuse
10. **Test edge cases** thoroughly

## Next Steps

- Explore the [API Reference](/api/) for detailed method documentation
- Check out [Advanced Features](advanced-features.md) for more complex features
- Review [Streaming](streaming.md) for real-time implementations 