<?php

/**
 * Feature Tests: Cache Control Accumulation Prevention
 *
 * These tests verify that cache control metadata from Prism responses
 * is not persisted in the database and doesn't accumulate in conversation history.
 * This prevents errors when exceeding provider limits (e.g., Anthropic's limit of 4).
 */

use ElliottLawson\ConversePrism\Models\Conversation;
use ElliottLawson\ConversePrism\Models\Message;
use ElliottLawson\ConversePrism\Tests\Models\TestUser;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\Enums\FinishReason;
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
        'title' => 'Test Cache Control',
        'metadata' => [
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
        ],
    ]);

    // Get the conversation using our extended model
    $this->conversation = Conversation::find($baseConversation->id);
});

it('does not persist cache control metadata when saving messages', function () {
    // Create a Prism response with cache control metadata
    $response = new class
    {
        public $text = 'Response with cache control';
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
                id: 'req_123',
                model: 'claude-3-5-sonnet'
            );
        }
    };

    // Add response with cache control metadata
    $this->conversation->addPrismResponse($response, [
        'cache_type' => 'ephemeral',
        'cache_control' => ['type' => 'ephemeral'],
        'provider_options' => ['anthropic' => ['cache_control' => ['type' => 'ephemeral']]],
        'other_metadata' => 'should persist'
    ]);

    // Get the saved message
    $message = $this->conversation->messages()->latest()->first();

    // Verify cache control metadata was NOT persisted
    expect($message->metadata)->not->toHaveKeys(['cache_type', 'cache_control', 'provider_options'])
        ->and($message->metadata['other_metadata'])->toBe('should persist')
        ->and($message->metadata)->toHaveKeys(['tokens', 'prompt_tokens', 'completion_tokens', 'finish_reason', 'model', 'provider_request_id']);
});

it('strips provider options when reconstructing messages from storage', function () {
    // Manually create a message with provider options in metadata (simulating old data)
    $message = $this->conversation->messages()->create([
        'role' => 'user',
        'content' => 'Test message',
        'metadata' => [
            'provider_options' => ['anthropic' => ['cache_control' => ['type' => 'ephemeral']]],
            'cache_control' => ['type' => 'ephemeral'],
            'normal_metadata' => 'keep this'
        ]
    ]);

    // Convert to Prism message
    $prismMessage = $message->toPrismMessage();

    // Use reflection to check provider options
    $reflection = new ReflectionClass($prismMessage);
    if ($reflection->hasProperty('providerOptions')) {
        $providerOptionsProperty = $reflection->getProperty('providerOptions');
        $providerOptionsProperty->setAccessible(true);
        $providerOptions = $providerOptionsProperty->getValue($prismMessage);

        // Provider options should be empty array
        expect($providerOptions)->toBe([]);
    }

    // Verify the message content is preserved
    expect($prismMessage)->toBeInstanceOf(UserMessage::class)
        ->and($prismMessage->content)->toBe('Test message');
});

it('prevents cache control accumulation in conversation history', function () {
    // Simulate multiple rounds of conversation with cache control
    for ($i = 1; $i <= 5; $i++) {
        // Add user message
        $this->conversation->addUserMessage("Question $i");

        // Create response with cache control
        $response = new class($i)
        {
            public $text;
            public $usage;
            public $finishReason = FinishReason::Stop;
            public $meta;

            public function __construct($i)
            {
                $this->text = "Answer $i";
                $this->usage = new Usage(
                    promptTokens: 50,
                    completionTokens: 50
                );
                $this->meta = new Meta(
                    id: "req_$i",
                    model: 'claude-3-5-sonnet'
                );
            }
        };

        // Add response with cache control metadata
        $this->conversation->addPrismResponse($response, [
            'cache_type' => 'ephemeral',
            'cache_control' => ['type' => 'ephemeral'],
            'provider_options' => ['anthropic' => ['cache_control' => ['type' => 'ephemeral']]]
        ]);
    }

    // Convert entire conversation to Prism messages
    $prismMessages = $this->conversation->toPrismMessages();

    // Verify we have 10 messages (5 user + 5 assistant)
    expect($prismMessages)->toHaveCount(10);

    // Check each message doesn't have provider options
    foreach ($prismMessages as $prismMessage) {
        $reflection = new ReflectionClass($prismMessage);
        if ($reflection->hasProperty('providerOptions')) {
            $providerOptionsProperty = $reflection->getProperty('providerOptions');
            $providerOptionsProperty->setAccessible(true);
            $providerOptions = $providerOptionsProperty->getValue($prismMessage);

            // Provider options should be empty
            expect($providerOptions)->toBe([]);
        }
    }

    // Verify no cache control in stored metadata
    $messages = $this->conversation->messages()->get();
    foreach ($messages as $message) {
        expect($message->metadata)->not->toHaveKeys(['cache_type', 'cache_control', 'provider_options']);
    }
});

it('handles tool call messages without persisting cache control', function () {
    $toolCalls = [
        [
            'id' => 'call_123',
            'name' => 'get_weather',
            'arguments' => ['location' => 'San Francisco'],
        ],
    ];

    $response = (object) [
        'toolCalls' => $toolCalls,
    ];

    // Add tool call with cache control metadata
    $this->conversation->addPrismResponse($response, [
        'cache_control' => ['type' => 'ephemeral'],
        'provider_options' => ['anthropic' => ['cache_control' => ['type' => 'ephemeral']]],
        'tool_metadata' => 'should persist'
    ]);

    $message = $this->conversation->messages()->latest()->first();

    // Verify cache control was not persisted
    expect($message->role->value)->toBe('tool_call')
        ->and($message->metadata)->not->toHaveKeys(['cache_control', 'provider_options'])
        ->and($message->metadata['tool_metadata'])->toBe('should persist');
});

it('handles tool result messages without persisting cache control', function () {
    $toolResults = [
        [
            'id' => 'call_123',
            'result' => 'Sunny, 72°F',
        ],
    ];

    $response = (object) [
        'toolResults' => $toolResults,
    ];

    // Add tool result with cache control metadata
    $this->conversation->addPrismResponse($response, [
        'cache_control' => ['type' => 'ephemeral'],
        'cache_type' => 'ephemeral',
        'result_metadata' => 'should persist'
    ]);

    $message = $this->conversation->messages()->latest()->first();

    // Verify cache control was not persisted
    expect($message->role->value)->toBe('tool_result')
        ->and($message->metadata)->not->toHaveKeys(['cache_control', 'cache_type'])
        ->and($message->metadata['result_metadata'])->toBe('should persist');
});

it('properly formats messages with empty provider options for all message types', function () {
    // Test User Message
    $userMessage = $this->conversation->messages()->create([
        'role' => 'user',
        'content' => 'User message',
        'metadata' => ['provider_options' => ['some' => 'options']]
    ]);
    $prismUser = $userMessage->toPrismMessage();
    expect($prismUser)->toBeInstanceOf(UserMessage::class);

    // Test System Message
    $systemMessage = $this->conversation->messages()->create([
        'role' => 'system',
        'content' => 'System message',
        'metadata' => ['cache_control' => ['type' => 'ephemeral']]
    ]);
    $prismSystem = $systemMessage->toPrismMessage();
    expect($prismSystem)->toBeInstanceOf(SystemMessage::class);

    // Test Assistant Message
    $assistantMessage = $this->conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'Assistant message',
        'metadata' => ['provider_options' => ['anthropic' => ['cache_control' => ['type' => 'ephemeral']]]]
    ]);
    $prismAssistant = $assistantMessage->toPrismMessage();
    expect($prismAssistant)->toBeInstanceOf(AssistantMessage::class);

    // Verify all messages have empty provider options
    foreach ([$prismUser, $prismSystem, $prismAssistant] as $prismMessage) {
        if (method_exists($prismMessage, 'withProviderOptions')) {
            $reflection = new ReflectionClass($prismMessage);
            if ($reflection->hasProperty('providerOptions')) {
                $providerOptionsProperty = $reflection->getProperty('providerOptions');
                $providerOptionsProperty->setAccessible(true);
                $providerOptions = $providerOptionsProperty->getValue($prismMessage);
                expect($providerOptions)->toBe([]);
            }
        }
    }
}); 