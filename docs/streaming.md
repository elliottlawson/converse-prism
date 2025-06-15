# Streaming

Real-time streaming responses provide a better user experience by showing AI responses as they're generated, rather than waiting for the complete response.

## Overview

Converse-Prism provides elegant streaming support through the `PrismStream` class, which handles:
- Automatic chunk collection
- Progress tracking
- Database persistence
- Error handling
- Metadata extraction

## Basic Streaming

### Simple Stream Example

```php
use Prism\Enums\Provider;

// Start a streaming response
$stream = $conversation->streamPrismResponse();

// Make the streaming request
$response = $conversation
    ->addUserMessage('Write a short story about a robot')
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream) {
        // Handle each chunk as it arrives
        $stream->append($chunk);
        
        // Output to user (e.g., via SSE, WebSocket, etc.)
        echo $chunk;
    });

// Complete the stream and save to database
$message = $stream->complete($response);
```

### Understanding the Flow

1. **Create a stream** - `streamPrismResponse()` creates a new message in "streaming" status
2. **Process chunks** - The callback receives each text chunk as it arrives
3. **Append chunks** - `append()` collects chunks and updates the message
4. **Complete stream** - `complete()` finalizes the message and extracts metadata

## Server-Sent Events (SSE)

The most common pattern for web applications is using Server-Sent Events:

```php
namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Prism\Enums\Provider;

class StreamController extends Controller
{
    public function stream(Request $request, Conversation $conversation)
    {
        return response()->stream(function () use ($request, $conversation) {
            $stream = $conversation->streamPrismResponse();
            
            try {
                $response = $conversation
                    ->addUserMessage($request->input('message'))
                    ->toPrismText()
                    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
                    ->stream(function ($chunk) use ($stream) {
                        $stream->append($chunk);
                        
                        // Send SSE formatted data
                        echo "data: " . json_encode([
                            'chunk' => $chunk,
                            'message_id' => $stream->getMessage()->id
                        ]) . "\n\n";
                        
                        // Flush output immediately
                        ob_flush();
                        flush();
                    });
                
                // Send completion event
                $message = $stream->complete($response);
                echo "data: " . json_encode([
                    'done' => true,
                    'message' => $message->toArray()
                ]) . "\n\n";
                
            } catch (\Exception $e) {
                // Handle errors
                $stream->fail($e->getMessage());
                echo "data: " . json_encode([
                    'error' => true,
                    'message' => $e->getMessage()
                ]) . "\n\n";
            }
            
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Disable Nginx buffering
        ]);
    }
}
```

### Frontend JavaScript Example

```javascript
const eventSource = new EventSource(`/api/conversations/${conversationId}/stream`);
const messageDiv = document.getElementById('ai-response');

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    if (data.chunk) {
        // Append chunk to display
        messageDiv.textContent += data.chunk;
    } else if (data.done) {
        // Stream completed
        eventSource.close();
        console.log('Complete message:', data.message);
    } else if (data.error) {
        // Handle error
        eventSource.close();
        console.error('Stream error:', data.message);
    }
};
```

## Broadcasting with Laravel Echo

For real-time updates across multiple clients:

```php
use App\Events\MessageChunkReceived;
use App\Events\MessageCompleted;

$stream = $conversation->streamPrismResponse([
    'channel' => "conversation.{$conversation->id}"
]);

$response = $conversation
    ->addUserMessage($request->input('message'))
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->stream(function ($chunk) use ($stream, $conversation) {
        $stream->append($chunk);
        
        // Broadcast to all listening clients
        broadcast(new MessageChunkReceived(
            $conversation,
            $stream->getMessage(),
            $chunk
        ))->toOthers();
    });

$message = $stream->complete($response);

// Broadcast completion
broadcast(new MessageCompleted($conversation, $message))->toOthers();
```

### Frontend with Laravel Echo

```javascript
// Listen for chunks
Echo.channel(`conversation.${conversationId}`)
    .listen('MessageChunkReceived', (e) => {
        appendToMessage(e.chunk);
    })
    .listen('MessageCompleted', (e) => {
        onMessageComplete(e.message);
    });
```

## Streaming with Progress

Track streaming progress and display to users:

```php
$totalChunks = 0;
$startTime = microtime(true);

$stream = $conversation->streamPrismResponse();

$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream, &$totalChunks, $startTime) {
        $stream->append($chunk);
        $totalChunks++;
        
        // Calculate metrics
        $elapsed = microtime(true) - $startTime;
        $charsPerSecond = strlen($stream->getMessage()->content) / $elapsed;
        
        // Send progress update
        echo "data: " . json_encode([
            'chunk' => $chunk,
            'progress' => [
                'chunks' => $totalChunks,
                'characters' => strlen($stream->getMessage()->content),
                'elapsed' => round($elapsed, 2),
                'speed' => round($charsPerSecond)
            ]
        ]) . "\n\n";
        
        ob_flush();
        flush();
    });
```

## Advanced Streaming Patterns

### Streaming with Fallback

Handle streaming failures gracefully:

```php
public function streamWithFallback(Request $request, Conversation $conversation)
{
    try {
        // Try streaming first
        return $this->streamResponse($request, $conversation);
    } catch (\Exception $e) {
        // Fallback to non-streaming
        Log::warning('Streaming failed, using fallback', [
            'error' => $e->getMessage(),
            'conversation_id' => $conversation->id
        ]);
        
        $response = $conversation
            ->addUserMessage($request->input('message'))
            ->toPrismText()
            ->using(Provider::OpenAI, 'gpt-4')
            ->asText();
            
        $conversation->addPrismResponse($response->text);
        
        return response()->json([
            'message' => $conversation->lastMessage,
            'streamed' => false
        ]);
    }
}
```

### Chunked Processing

Process chunks before sending them:

```php
$buffer = '';
$sentences = [];

$stream = $conversation->streamPrismResponse();

$response = $conversation
    ->toPrismText()
    ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
    ->stream(function ($chunk) use ($stream, &$buffer, &$sentences) {
        $stream->append($chunk);
        $buffer .= $chunk;
        
        // Process complete sentences
        while (preg_match('/^(.*?[.!?])\s*/s', $buffer, $matches)) {
            $sentence = $matches[1];
            $buffer = substr($buffer, strlen($matches[0]));
            $sentences[] = $sentence;
            
            // Send complete sentence
            echo "data: " . json_encode([
                'sentence' => $sentence,
                'sentence_count' => count($sentences)
            ]) . "\n\n";
            
            ob_flush();
            flush();
        }
    });

// Don't forget remaining buffer
if ($buffer) {
    echo "data: " . json_encode(['sentence' => $buffer]) . "\n\n";
}
```

### Streaming with Rate Limiting

Control the rate of updates sent to the client:

```php
$lastUpdate = 0;
$updateInterval = 0.1; // Update every 100ms
$buffer = '';

$stream = $conversation->streamPrismResponse();

$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->stream(function ($chunk) use ($stream, &$lastUpdate, &$buffer, $updateInterval) {
        $stream->append($chunk);
        $buffer .= $chunk;
        
        $now = microtime(true);
        if ($now - $lastUpdate >= $updateInterval) {
            // Send buffered content
            echo "data: " . json_encode(['chunk' => $buffer]) . "\n\n";
            ob_flush();
            flush();
            
            $buffer = '';
            $lastUpdate = $now;
        }
    });

// Send any remaining buffer
if ($buffer) {
    echo "data: " . json_encode(['chunk' => $buffer]) . "\n\n";
}
```

## Error Handling

### Stream Failure Handling

```php
$stream = $conversation->streamPrismResponse();

try {
    $response = $conversation
        ->toPrismText()
        ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
        ->stream(function ($chunk) use ($stream) {
            $stream->append($chunk);
            // Process chunk...
        });
        
    $message = $stream->complete($response);
    
} catch (\Prism\Exceptions\PrismException $e) {
    // API-specific error
    $stream->fail($e->getMessage(), [
        'error_type' => 'api_error',
        'provider' => 'anthropic',
        'status_code' => $e->getCode()
    ]);
    
} catch (\Exception $e) {
    // General error
    $stream->fail($e->getMessage(), [
        'error_type' => 'general_error'
    ]);
}
```

### Timeout Handling

```php
$timeout = 30; // 30 seconds
$startTime = time();

$stream = $conversation->streamPrismResponse();

$response = $conversation
    ->toPrismText()
    ->using(Provider::OpenAI, 'gpt-4')
    ->stream(function ($chunk) use ($stream, $timeout, $startTime) {
        if (time() - $startTime > $timeout) {
            throw new \Exception('Stream timeout exceeded');
        }
        
        $stream->append($chunk);
        // Process chunk...
    });
```

## Metadata and Analytics

The streaming system automatically tracks useful metadata:

```php
$message = $stream->complete($response);

// Access streaming metadata
$metadata = $message->metadata;

echo $metadata['stream_chunks'];      // Number of chunks received
echo $metadata['stream_duration'];     // Total streaming time in seconds
echo $metadata['completion_tokens'];   // Tokens in the response
echo $metadata['model'];              // Model used
echo $metadata['finish_reason'];      // Why streaming stopped
```

### Custom Stream Metadata

Add your own metadata during streaming:

```php
$stream = $conversation->streamPrismResponse([
    'user_agent' => request()->userAgent(),
    'ip_address' => request()->ip(),
    'session_id' => session()->getId()
]);

// The metadata is stored with the message
```

## Best Practices

### 1. Always Use Try-Catch
```php
try {
    // Streaming code
} catch (\Exception $e) {
    $stream->fail($e->getMessage());
    // Handle error appropriately
}
```

### 2. Flush Output Immediately
```php
echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
ob_flush();
flush();
```

### 3. Set Appropriate Headers
```php
return response()->stream(function () {
    // ...
}, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no', // Important for Nginx
]);
```

### 4. Handle Connection Drops
```php
// Check if client is still connected
if (connection_aborted()) {
    $stream->fail('Client disconnected');
    return;
}
```

### 5. Test Streaming Locally
Some local development servers buffer output. Test with:
```bash
php artisan serve --host=0.0.0.0
```

## Next Steps

- Explore [Advanced Features](advanced-features.md) for tools and structured outputs
- See [Examples](examples.md) for complete streaming implementations
- Check the [API Reference](api-reference.md) for all streaming methods 