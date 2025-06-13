<?php

/**
 * Feature Tests: Prism Response Handling
 *
 * These tests verify that various Prism response types are correctly
 * handled and stored in Converse:
 * - Text responses with metadata (usage, model, etc.)
 * - Tool call responses
 * - Complex conversations with multiple message types
 * - Streaming responses
 * - Edge cases (empty responses, missing metadata)
 */

use ElliottLawson\ConversePrism\Tests\Models\TestUser;
use ElliottLawson\ConversePrism\Models\Conversation;
use ElliottLawson\ConversePrism\Models\Message;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

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

it('handles a complete Prism text response with all metadata', function () {
    // Simulate a Prism response object structure
    $prismResponse = new class
    {
        public $text = 'This is the assistant response';

        public $finishReason = 'stop';

        public $usage;

        public $response = [
            'id' => 'resp_123',
            'model' => 'claude-3-5-sonnet-20241022',
        ];

        public function __construct()
        {
            $this->usage = new class
            {
                public $promptTokens = 25;

                public $completionTokens = 15;

                public $totalTokens = 40;
            };
        }
    };

    $message = $this->conversation->addPrismResponse($prismResponse);

    expect($message->role->value)->toBe('assistant')
        ->and($message->content)->toBe('This is the assistant response')
        ->and($message->metadata)->toMatchArray([
            'tokens' => 40,
            'prompt_tokens' => 25,
            'completion_tokens' => 15,
            'finish_reason' => 'stop',
            'model' => 'claude-3-5-sonnet-20241022',
            'provider_request_id' => 'resp_123',
        ]);
});

it('formats complex conversations for Prism correctly', function () {
    // Build a conversation with various message types
    $this->conversation->addSystemMessage('You are a helpful assistant');
    $this->conversation->addUserMessage('What is the weather?');
    $this->conversation->addToolCallMessage(json_encode([
        [
            'id' => 'call_weather',
            'name' => 'get_weather',
            'arguments' => ['location' => 'San Francisco', 'unit' => 'fahrenheit'],
        ],
    ]));
    $this->conversation->addToolResultMessage(json_encode([
        [
            'id' => 'call_weather',
            'result' => json_encode(['temp' => 72, 'condition' => 'sunny']),
        ],
    ]));
    $this->conversation->addAssistantMessage('The weather in San Francisco is 72°F and sunny.');

    $prismMessages = $this->conversation->toPrism();

    expect($prismMessages)->toHaveCount(5)
        ->and($prismMessages[0])->toBeInstanceOf(SystemMessage::class)
        ->and($prismMessages[0]->content)->toBe('You are a helpful assistant')
        ->and($prismMessages[1])->toBeInstanceOf(UserMessage::class)
        ->and($prismMessages[1]->content)->toBe('What is the weather?')
        ->and($prismMessages[2])->toBeInstanceOf(AssistantMessage::class)
        ->and($prismMessages[2]->content)->toBe('')
        ->and($prismMessages[2]->toolCalls)->toHaveCount(1)
        ->and($prismMessages[2]->toolCalls[0]->id)->toBe('call_weather')
        ->and($prismMessages[3])->toBeInstanceOf(ToolResultMessage::class)
        ->and($prismMessages[3]->toolResults)->toHaveCount(1)
        ->and($prismMessages[3]->toolResults[0]->toolCallId)->toBe('call_weather')
        ->and($prismMessages[4])->toBeInstanceOf(AssistantMessage::class)
        ->and($prismMessages[4]->content)->toBe('The weather in San Francisco is 72°F and sunny.');
});

it('handles Prism tool call responses correctly', function () {
    $prismResponse = new class
    {
        public $text = '';

        public $toolCalls = [
            [
                'id' => 'call_123',
                'name' => 'search_web',
                'arguments' => '{"query": "Laravel Prism integration"}',
            ],
            [
                'id' => 'call_124',
                'name' => 'calculate',
                'arguments' => '{"expression": "2 + 2"}',
            ],
        ];

        public $usage;

        public function __construct()
        {
            $this->usage = new class
            {
                public $promptTokens = 50;

                public $completionTokens = 30;

                public $totalTokens = 80;
            };
        }
    };

    $message = $this->conversation->addPrismResponse($prismResponse);

    expect($message->role->value)->toBe('tool_call')
        ->and($message->content)->toBeJson()
        ->and(json_decode($message->content, true))->toBe($prismResponse->toolCalls)
        ->and($message->metadata)->toMatchArray([
            'tokens' => 80,
            'prompt_tokens' => 50,
            'completion_tokens' => 30,
        ]);
});

it('handles streaming responses with proper chunk management', function () {
    $stream = $this->conversation->streamPrismResponse(['model' => 'gpt-4']);

    // Simulate streaming chunks
    $chunks = ['The', ' weather', ' in', ' Paris', ' is', ' cloudy', '.'];
    foreach ($chunks as $chunk) {
        $stream->append($chunk);
    }

    // Simulate final Prism response
    $finalResponse = new class
    {
        public $text = 'The weather in Paris is cloudy.';

        public $finishReason = 'stop';

        public $usage;

        public function __construct()
        {
            $this->usage = new class
            {
                public $promptTokens = 20;

                public $completionTokens = 8;

                public $totalTokens = 28;
            };
        }
    };

    $message = $stream->complete($finalResponse);

    expect($message->content)->toBe('The weather in Paris is cloudy.')
        ->and($message->metadata)->toMatchArray([
            'model' => 'gpt-4',
            'streamed' => true,
            'chunks' => 7,
            'tokens' => 28,
            'prompt_tokens' => 20,
            'completion_tokens' => 8,
            'finish_reason' => 'stop',
        ])
        ->and($message->metadata)->toHaveKey('stream_duration_ms');
});

it('preserves message integrity through format conversion', function () {
    // Test that messages maintain their data through conversion
    $originalContent = 'Test message with special characters: "quotes", \'apostrophes\', & symbols!';
    $this->conversation->addUserMessage($originalContent);

    $prismMessages = $this->conversation->toPrism();

    expect($prismMessages[0])->toBeInstanceOf(UserMessage::class)
        ->and($prismMessages[0]->content)->toBe($originalContent);
});

it('handles empty tool arguments correctly', function () {
    $prismResponse = new class
    {
        public $toolCalls = [
            [
                'id' => 'call_empty',
                'name' => 'get_time',
                'arguments' => '{}',
            ],
        ];
    };

    $message = $this->conversation->addPrismResponse($prismResponse);

    $storedToolCalls = json_decode($message->content, true);
    expect($storedToolCalls[0]['arguments'])->toBe('{}');
});

it('handles multi-step Prism responses', function () {
    $prismResponse = new class
    {
        public $text = 'Final response after multiple steps';

        public $steps = ['step1', 'step2', 'step3']; // Simplified - real steps would be objects

        public $usage;

        public function __construct()
        {
            $this->usage = new class
            {
                public $totalTokens = 150;
            };
        }
    };

    $message = $this->conversation->addPrismResponse($prismResponse);

    expect($message->metadata['steps'])->toBe(3)
        ->and($message->metadata['tokens'])->toBe(150);
});

it('correctly handles missing or null Prism response fields', function () {
    $minimalResponse = new class
    {
        public $text = 'Response with minimal data';
    };

    $message = $this->conversation->addPrismResponse($minimalResponse);

    expect($message->content)->toBe('Response with minimal data')
        ->and($message->metadata)->toBe([]);
});

it('maintains conversation context for Prism', function () {
    // Build a conversation
    $this->conversation->addUserMessage('Hi');
    $this->conversation->addAssistantMessage('Hello! How can I help?');
    $this->conversation->addUserMessage('Tell me a joke');

    // Get messages in Prism format
    $prismMessages = $this->conversation->toPrism();

    // Verify the context is maintained in order
    expect($prismMessages)->toHaveCount(3)
        ->and($prismMessages[0])->toBeInstanceOf(UserMessage::class)
        ->and($prismMessages[0]->content)->toBe('Hi')
        ->and($prismMessages[1])->toBeInstanceOf(AssistantMessage::class)
        ->and($prismMessages[1]->content)->toBe('Hello! How can I help?')
        ->and($prismMessages[2])->toBeInstanceOf(UserMessage::class)
        ->and($prismMessages[2]->content)->toBe('Tell me a joke');

    // Simulate Prism response
    $jokeResponse = new class
    {
        public $text = 'Why did the developer go broke? Because he used up all his cache!';
    };

    $this->conversation->addPrismResponse($jokeResponse);

    // Verify full conversation
    $allMessages = $this->conversation->toPrism();
    expect($allMessages)->toHaveCount(4)
        ->and($allMessages[3])->toBeInstanceOf(AssistantMessage::class)
        ->and($allMessages[3]->content)->toBe('Why did the developer go broke? Because he used up all his cache!');
});
