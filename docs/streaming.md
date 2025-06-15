# Streaming

Converse Prism provides elegant streaming support through the `PrismStream` class, which handles chunk collection, progress tracking, and database persistence.

## Basic Usage

The `streamPrismResponse()` method creates a streaming-enabled message that collects chunks as they arrive:

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

## How It Works

1. **Create a stream** - `streamPrismResponse()` creates a new message in "streaming" status
2. **Process chunks** - The callback receives each text chunk as it arrives
3. **Append chunks** - `append()` collects chunks and updates the message
4. **Complete stream** - `complete()` finalizes the message and extracts metadata

## Server-Sent Events Example

A common pattern for web applications:

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

## Error Handling

The `PrismStream` class provides a `fail()` method for error handling:

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
    
} catch (\Exception $e) {
    // Mark the stream as failed with optional metadata
    $stream->fail($e->getMessage(), [
        'error_type' => 'api_error',
        'status_code' => $e->getCode()
    ]);
}
```

## Metadata Tracking

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

You can also add custom metadata when creating the stream:

```php
$stream = $conversation->streamPrismResponse([
    'user_agent' => request()->userAgent(),
    'ip_address' => request()->ip(),
    'session_id' => session()->getId()
]);
```

## Next Steps

- Explore [Advanced Features](advanced-features.md) for tools and structured outputs
- See [Examples](examples.md) for complete streaming implementations
- Check the [API Reference](api-reference.md) for all streaming methods 