<?php

/**
 * Feature Tests: Conversation Prism Methods
 *
 * These tests verify that the methods added to Conversation and Message
 * models work correctly:
 * - toPrism() converts messages to Prism format
 * - addPrismResponse() saves Prism responses as messages
 * - streamPrismResponse() handles streaming responses
 */

use ElliottLawson\ConversePrism\Models\Conversation;
use ElliottLawson\ConversePrism\Models\Message;
use ElliottLawson\ConversePrism\Support\PrismStream;
use ElliottLawson\ConversePrism\Tests\Models\TestUser;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Text\PendingRequest as PendingTextRequest;
use Prism\Prism\Structured\PendingRequest as PendingStructuredRequest;
use Prism\Prism\Embeddings\PendingRequest as PendingEmbeddingRequest;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    // Create a test user
    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // Create a conversation using the HasAIConversations trait
    $baseConversation = $this->user->startConversation([
        'title' => 'Test Conversation',
        'metadata' => [
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
        ],
    ]);

    // Get the conversation using our extended model
    $this->conversation = Conversation::find($baseConversation->id);
});

it('converts conversation messages to prism format', function () {
    // Add messages to conversation
    $this->conversation->addUserMessage('Hello AI');
    $this->conversation->addAssistantMessage('Hello human');
    $this->conversation->addSystemMessage('You are helpful');

    $prismMessages = $this->conversation->toPrism();

    expect($prismMessages)->toBeArray()
        ->toHaveCount(3)
        ->and($prismMessages[0])->toBeInstanceOf(UserMessage::class)
        ->and($prismMessages[0]->content)->toBe('Hello AI')
        ->and($prismMessages[1])->toBeInstanceOf(AssistantMessage::class)
        ->and($prismMessages[1]->content)->toBe('Hello human')
        ->and($prismMessages[2])->toBeInstanceOf(SystemMessage::class)
        ->and($prismMessages[2]->content)->toBe('You are helpful');
});

it('converts a single message to prism format', function () {
    $this->conversation->addUserMessage('Test message');

    // Get the message using our extended model
    $message = Message::latest()->first();

    $prismFormat = $message->toPrism();

    expect($prismFormat)->toBeInstanceOf(UserMessage::class)
        ->and($prismFormat->content)->toBe('Test message');
});

it('handles tool call messages correctly', function () {
    $toolCalls = [
        [
            'id' => 'call_123',
            'name' => 'get_weather',
            'arguments' => ['location' => 'San Francisco'],
        ],
    ];

    $this->conversation->addToolCallMessage(json_encode($toolCalls));

    // Get the message using our extended model
    $message = Message::latest()->first();

    $prismFormat = $message->toPrism();

    expect($prismFormat)->toBeInstanceOf(AssistantMessage::class)
        ->and($prismFormat->content)->toBe('')
        ->and($prismFormat->toolCalls)->toHaveCount(1)
        ->and($prismFormat->toolCalls[0]->id)->toBe('call_123')
        ->and($prismFormat->toolCalls[0]->name)->toBe('get_weather');
});

it('handles tool result messages correctly', function () {
    $toolResults = [
        [
            'id' => 'call_123',
            'result' => 'Sunny, 72°F',
        ],
    ];

    $this->conversation->addToolResultMessage(json_encode($toolResults));

    // Get the message using our extended model
    $message = Message::latest()->first();

    $prismFormat = $message->toPrism();

    expect($prismFormat)->toBeInstanceOf(ToolResultMessage::class)
        ->and($prismFormat->toolResults)->toHaveCount(1)
        ->and($prismFormat->toolResults[0]->toolCallId)->toBe('call_123')
        ->and($prismFormat->toolResults[0]->result)->toBe('Sunny, 72°F');
});

it('adds prism response as assistant message', function () {
    $response = new class
    {
        public $text = 'This is the AI response';

        public $usage;

        public $finishReason = FinishReason::Stop;

        public $meta;

        public function __construct()
        {
            $this->usage = new Usage(
                promptTokens: 50,
                completionTokens: 50
            );

            $this->meta = new Meta(
                id: 'req_456',
                model: 'gpt-4'
            );
        }
    };

    $message = $this->conversation->addPrismResponse($response, ['custom' => 'metadata']);

    expect($message->role->value)->toBe('assistant')
        ->and($message->content)->toBe('This is the AI response')
        ->and($message->metadata)->toMatchArray([
            'tokens' => 100,  // calculated: 50 + 50
            'prompt_tokens' => 50,
            'completion_tokens' => 50,
            'finish_reason' => FinishReason::Stop->name,
            'model' => 'gpt-4',
            'provider_request_id' => 'req_456',
            'custom' => 'metadata',
        ]);
});

it('adds prism tool call response correctly', function () {
    $toolCalls = [
        [
            'id' => 'call_456',
            'name' => 'search',
            'arguments' => ['query' => 'Laravel'],
        ],
    ];

    $response = (object) [
        'toolCalls' => $toolCalls,
    ];

    $message = $this->conversation->addPrismResponse($response);

    expect($message->role->value)->toBe('tool_call')
        ->and(json_decode($message->content, true))->toBe($toolCalls);
});

it('creates prism stream for streaming responses', function () {
    $stream = $this->conversation->streamPrismResponse(['model' => 'gpt-4']);

    expect($stream)->toBeInstanceOf(PrismStream::class)
        ->and($stream->getMessage()->role->value)->toBe('assistant')
        ->and($stream->getMessage()->metadata)->toMatchArray(['model' => 'gpt-4']);
});

it('handles streaming chunks correctly', function () {
    $stream = $this->conversation->streamPrismResponse();

    $stream->append('Hello')
        ->append(' ')
        ->append('world!');

    $message = $stream->complete();

    expect($message->content)->toBe('Hello world!')
        ->and($message->metadata)->toHaveKeys(['streamed', 'chunks', 'stream_duration_ms'])
        ->and($message->metadata['chunks'])->toBe(3)
        ->and($message->metadata['streamed'])->toBeTrue();
});

it('handles streaming failure correctly', function () {
    $stream = $this->conversation->streamPrismResponse();

    $stream->append('Partial response');

    $message = $stream->fail('API Error', ['error_code' => 500]);

    expect($message->metadata)->toMatchArray([
        'error' => 'API Error',
        'chunks_received' => 1,
        'error_code' => 500,
    ])
        ->and($message->metadata)->toHaveKey('stream_duration_ms');
});

it('throws exception when calling addPrismResponse on message model', function () {
    $this->conversation->addUserMessage('Test');

    // Get the message using our extended model
    $message = Message::latest()->first();

    expect(fn () => $message->addPrismResponse((object) ['text' => 'test']))
        ->toThrow(BadMethodCallException::class, 'addPrismResponse can only be called on Conversation model');
});

it('throws exception when calling streamPrismResponse on message model', function () {
    $this->conversation->addUserMessage('Test');

    // Get the message using our extended model
    $message = Message::latest()->first();

    expect(fn () => $message->streamPrismResponse())
        ->toThrow(BadMethodCallException::class, 'streamPrismResponse can only be called on Conversation model');
});

describe('Message Chaining Methods', function () {
    it('chains addUserMessage with toPrismMessage', function () {
        $prismMessage = $this->conversation->addUserMessage('Hello world')->toPrismMessage();

        expect($prismMessage)->toBeInstanceOf(UserMessage::class)
            ->and($prismMessage->content)->toBe('Hello world');

        // Verify message was actually added to conversation
        $lastMessage = $this->conversation->messages()->latest()->first();
        expect($lastMessage->content)->toBe('Hello world')
            ->and($lastMessage->role->value)->toBe('user');
    });

    it('chains addSystemMessage with toPrismMessage', function () {
        $prismMessage = $this->conversation->addSystemMessage('You are helpful')->toPrismMessage();

        expect($prismMessage)->toBeInstanceOf(SystemMessage::class)
            ->and($prismMessage->content)->toBe('You are helpful');

        // Verify message was actually added to conversation
        $lastMessage = $this->conversation->messages()->latest()->first();
        expect($lastMessage->content)->toBe('You are helpful')
            ->and($lastMessage->role->value)->toBe('system');
    });

    it('chains addAssistantMessage with toPrismMessage', function () {
        $prismMessage = $this->conversation->addAssistantMessage('I can help with that')->toPrismMessage();

        expect($prismMessage)->toBeInstanceOf(AssistantMessage::class)
            ->and($prismMessage->content)->toBe('I can help with that');

        // Verify message was actually added to conversation
        $lastMessage = $this->conversation->messages()->latest()->first();
        expect($lastMessage->content)->toBe('I can help with that')
            ->and($lastMessage->role->value)->toBe('assistant');
    });

    it('passes metadata when chaining message creation', function () {
        $metadata = ['model' => 'gpt-4', 'tokens' => 100];
        
        $prismMessage = $this->conversation->addUserMessage('Test message', $metadata)->toPrismMessage();

        expect($prismMessage)->toBeInstanceOf(UserMessage::class);

        $lastMessage = $this->conversation->messages()->latest()->first();
        expect($lastMessage->metadata)->toMatchArray($metadata);
    });

    it('throws exception when calling toPrismMessage on conversation model', function () {
        expect(fn () => $this->conversation->toPrismMessage())
            ->toThrow(BadMethodCallException::class, 'toPrismMessage can only be called on Message model. Use toPrism() on Conversation model instead.');
    });

    it('can convert a conversation to a prism object', function () {
        $this->conversation->addSystemMessage('You are helpful assistant who writes poems');
        $this->conversation->addUserMessage('write a poem about the sea');
        
        $result = $this->conversation->toPrismText();

        expect($result)->toBeInstanceOf(PendingTextRequest::class);
    });
});

describe('Prism Facade Integration', function () {
    it('converts conversation to PendingTextRequest with messages', function () {
        $this->conversation->addSystemMessage('You are helpful');
        $this->conversation->addUserMessage('Hello world');
        
        $pendingRequest = $this->conversation->toPrismText();
        
        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);
        
        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'asText'))->toBeTrue();
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
    });

    it('converts conversation to PendingStructuredRequest with messages', function () {
        $this->conversation->addSystemMessage('You are helpful');
        $this->conversation->addUserMessage('Generate a JSON response');
        
        $pendingRequest = $this->conversation->toPrismStructured();
        
        expect($pendingRequest)->toBeInstanceOf(PendingStructuredRequest::class);
        
        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
        expect(method_exists($pendingRequest, 'withSchema'))->toBeTrue();
    });

    it('converts conversation to PendingEmbeddingRequest using last user message', function () {
        $this->conversation->addUserMessage('First message');
        $this->conversation->addSystemMessage('System response');
        $this->conversation->addUserMessage('Second message for embedding');
        
        $pendingRequest = $this->conversation->toPrismEmbeddings();
        
        expect($pendingRequest)->toBeInstanceOf(PendingEmbeddingRequest::class);
        
        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'fromInput'))->toBeTrue();
        expect(method_exists($pendingRequest, 'asEmbeddings'))->toBeTrue();
    });

    it('allows full Prism API chaining after toPrismText', function () {
        $this->conversation->addUserMessage('Test message');
        
        $pendingRequest = $this->conversation
            ->toPrismText()
            ->withMaxTokens(100)
            ->usingTemperature(0.5);
            
        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);
        expect(method_exists($pendingRequest, 'asText'))->toBeTrue();
        expect(method_exists($pendingRequest, 'asStream'))->toBeTrue();
    });

    it('throws exception when calling toPrism methods on message model', function () {
        $this->conversation->addUserMessage('Test');
        $message = Message::latest()->first();

        expect(fn () => $message->toPrismText())
            ->toThrow(BadMethodCallException::class, 'toPrismText can only be called on Conversation model');

        expect(fn () => $message->toPrismStructured())
            ->toThrow(BadMethodCallException::class, 'toPrismStructured can only be called on Conversation model');

        expect(fn () => $message->toPrismEmbeddings())
            ->toThrow(BadMethodCallException::class, 'toPrismEmbeddings can only be called on Conversation model');
    });
});
