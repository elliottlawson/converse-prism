<?php

/**
 * Feature Tests: Conversation Prism Message Conversion
 *
 * These tests verify core message conversion functionality:
 * - toPrism() converts conversation messages to Prism format
 * - toPrismMessage() converts individual messages to Prism format
 * - addPrismResponse() saves Prism responses as messages
 * - streamPrismResponse() handles streaming responses
 * - Fluent interface for building conversations
 */

use ElliottLawson\ConversePrism\Models\Conversation;
use ElliottLawson\ConversePrism\Models\Message;
use ElliottLawson\ConversePrism\Support\PrismStream;
use ElliottLawson\ConversePrism\Tests\Models\TestUser;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\PendingRequest as PendingTextRequest;
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

    $prismMessages = $this->conversation->toPrismMessages();

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

    $prismFormat = $message->toPrismMessage();

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

    $prismFormat = $message->toPrismMessage();

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

    $prismFormat = $message->toPrismMessage();

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

    $this->conversation->addPrismResponse($response, ['custom' => 'metadata']);
    $message = $this->conversation->messages()->latest()->first();

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

    $this->conversation->addPrismResponse($response);
    $message = $this->conversation->messages()->latest()->first();

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

describe('Message Conversion Methods', function () {
    it('can convert individual messages using toPrismMessage', function () {
        $this->conversation->addUserMessage('Hello world');
        $message = $this->conversation->messages()->latest()->first();

        $prismMessage = $message->toPrismMessage();

        expect($prismMessage)->toBeInstanceOf(UserMessage::class)
            ->and($prismMessage->content)->toBe('Hello world');
    });

    it('throws exception when calling toPrismMessage on conversation model', function () {
        expect(fn () => $this->conversation->toPrismMessage())
            ->toThrow(BadMethodCallException::class, 'toPrismMessage can only be called on Message model. Use toPrismMessages() on Conversation model instead.');
    });

    it('can convert a conversation to a prism object', function () {
        $this->conversation->addSystemMessage('You are helpful assistant who writes poems');
        $this->conversation->addUserMessage('write a poem about the sea');

        $result = $this->conversation->toPrismText();

        expect($result)->toBeInstanceOf(PendingTextRequest::class);
    });
});

describe('Fluent Interface', function () {
    it('chains multiple message additions using native fluent interface', function () {
        $conversation = $this->conversation
            ->addSystemMessage('You are a helpful assistant')
            ->addUserMessage('Hello world')
            ->addAssistantMessage('Hi there');

        expect($conversation)->toBeInstanceOf(Conversation::class);

        // Verify all messages were added to conversation
        $messages = $conversation->messages()->orderBy('created_at')->get();
        expect($messages)->toHaveCount(3);
        expect($messages[0]->role->value)->toBe('system');
        expect($messages[0]->content)->toBe('You are a helpful assistant');
        expect($messages[1]->role->value)->toBe('user');
        expect($messages[1]->content)->toBe('Hello world');
        expect($messages[2]->role->value)->toBe('assistant');
        expect($messages[2]->content)->toBe('Hi there');
    });

    it('can configure Prism text request fluently', function () {
        $pendingRequest = $this->conversation
            ->addUserMessage('Test message')
            ->toPrismText()
            ->withMaxTokens(2000);

        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);

        // Verify the pending request has the expected configuration methods
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
        expect(method_exists($pendingRequest, 'withMaxTokens'))->toBeTrue();
        expect(method_exists($pendingRequest, 'usingTemperature'))->toBeTrue();
    });

    it('passes metadata when using native fluent interface', function () {
        $metadata = ['source' => 'fluent-test'];

        $this->conversation->addUserMessage('Test message', $metadata);

        $lastMessage = $this->conversation->messages()->latest()->first();
        expect($lastMessage->metadata)->toMatchArray($metadata);
    });
});

describe('System Message Handling in toPrism Methods', function () {
    it('separates system messages and passes them individually to withSystemPrompts for toPrismText', function () {
        // Add multiple system messages mixed with other messages
        $this->conversation
            ->addSystemMessage('You are a helpful assistant')
            ->addSystemMessage('You speak in a friendly tone')
            ->addUserMessage('Hello, how are you?')
            ->addAssistantMessage('I am doing well, thank you!')
            ->addSystemMessage('You provide concise answers');

        $pendingRequest = $this->conversation->toPrismText();

        // Use reflection to verify the internal state
        $reflection = new ReflectionClass($pendingRequest);

        // Check if withSystemPrompts was called with individual system messages
        if ($reflection->hasProperty('systemPrompts')) {
            $systemPromptsProperty = $reflection->getProperty('systemPrompts');
            $systemPromptsProperty->setAccessible(true);
            $systemPrompts = $systemPromptsProperty->getValue($pendingRequest);

            // Verify we have 3 individual system messages
            expect($systemPrompts)->toHaveCount(3);
            expect($systemPrompts[0])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[0]->content)->toBe('You are a helpful assistant');
            expect($systemPrompts[1])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[1]->content)->toBe('You speak in a friendly tone');
            expect($systemPrompts[2])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[2]->content)->toBe('You provide concise answers');
        }

        // Check that only non-system messages are passed to withMessages
        if ($reflection->hasProperty('messages')) {
            $messagesProperty = $reflection->getProperty('messages');
            $messagesProperty->setAccessible(true);
            $messages = $messagesProperty->getValue($pendingRequest);

            // Should have 2 messages (user and assistant)
            expect($messages)->toHaveCount(2);
            expect($messages[0])->toBeInstanceOf(UserMessage::class);
            expect($messages[1])->toBeInstanceOf(AssistantMessage::class);
        }
    });

    it('separates system messages and passes them individually to withSystemPrompts for toPrismStructured', function () {
        // Add multiple system messages
        $this->conversation
            ->addSystemMessage('You extract structured data')
            ->addSystemMessage('You return JSON format')
            ->addUserMessage('Extract data from this text');

        $pendingRequest = $this->conversation->toPrismStructured();

        // Use reflection to verify the internal state
        $reflection = new ReflectionClass($pendingRequest);

        // Check if withSystemPrompts was called with individual system messages
        if ($reflection->hasProperty('systemPrompts')) {
            $systemPromptsProperty = $reflection->getProperty('systemPrompts');
            $systemPromptsProperty->setAccessible(true);
            $systemPrompts = $systemPromptsProperty->getValue($pendingRequest);

            // Verify we have 2 individual system messages
            expect($systemPrompts)->toHaveCount(2);
            expect($systemPrompts[0])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[0]->content)->toBe('You extract structured data');
            expect($systemPrompts[1])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[1]->content)->toBe('You return JSON format');
        }

        // Check messages
        if ($reflection->hasProperty('messages')) {
            $messagesProperty = $reflection->getProperty('messages');
            $messagesProperty->setAccessible(true);
            $messages = $messagesProperty->getValue($pendingRequest);

            // Should only have the user message
            expect($messages)->toHaveCount(1);
            expect($messages[0])->toBeInstanceOf(UserMessage::class);
        }
    });

    it('handles conversations with no system messages', function () {
        $this->conversation
            ->addUserMessage('Hello')
            ->addAssistantMessage('Hi there');

        $pendingRequest = $this->conversation->toPrismText();

        $reflection = new ReflectionClass($pendingRequest);

        // Check system prompts
        if ($reflection->hasProperty('systemPrompts')) {
            $systemPromptsProperty = $reflection->getProperty('systemPrompts');
            $systemPromptsProperty->setAccessible(true);
            $systemPrompts = $systemPromptsProperty->getValue($pendingRequest);

            // No system prompts should be set
            expect($systemPrompts)->toBeEmpty();
        }

        // Check messages
        if ($reflection->hasProperty('messages')) {
            $messagesProperty = $reflection->getProperty('messages');
            $messagesProperty->setAccessible(true);
            $messages = $messagesProperty->getValue($pendingRequest);

            // All messages should be in the messages array
            expect($messages)->toHaveCount(2);
            expect($messages[0])->toBeInstanceOf(UserMessage::class);
            expect($messages[1])->toBeInstanceOf(AssistantMessage::class);
        }
    });

    it('handles conversations with only system messages', function () {
        $this->conversation
            ->addSystemMessage('System instruction 1')
            ->addSystemMessage('System instruction 2');

        $pendingRequest = $this->conversation->toPrismText();

        $reflection = new ReflectionClass($pendingRequest);

        // Check system prompts
        if ($reflection->hasProperty('systemPrompts')) {
            $systemPromptsProperty = $reflection->getProperty('systemPrompts');
            $systemPromptsProperty->setAccessible(true);
            $systemPrompts = $systemPromptsProperty->getValue($pendingRequest);

            // Should have 2 individual system messages
            expect($systemPrompts)->toHaveCount(2);
            expect($systemPrompts[0])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[0]->content)->toBe('System instruction 1');
            expect($systemPrompts[1])->toBeInstanceOf(SystemMessage::class);
            expect($systemPrompts[1]->content)->toBe('System instruction 2');
        }

        // Check messages
        if ($reflection->hasProperty('messages')) {
            $messagesProperty = $reflection->getProperty('messages');
            $messagesProperty->setAccessible(true);
            $messages = $messagesProperty->getValue($pendingRequest);

            // Messages array should be empty or not set
            if ($messages !== null) {
                expect($messages)->toBeEmpty();
            }
        }
    });
});
