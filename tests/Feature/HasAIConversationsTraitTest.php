<?php

/**
 * Feature Tests: HasAIConversations Trait
 *
 * These tests verify that our extended HasAIConversations trait
 * automatically returns Prism-enabled conversations
 */

use ElliottLawson\ConversePrism\Models\Conversation;
use ElliottLawson\ConversePrism\Tests\Models\TestUser;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

beforeEach(function () {
    // Create a test user
    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('returns prism-enabled conversations from startConversation', function () {
    $conversation = $this->user->startConversation([
        'title' => 'Test Conversation',
    ]);

    // Should be our extended Conversation model
    expect($conversation)->toBeInstanceOf(Conversation::class);

    // Verify Prism methods are available
    expect(method_exists($conversation, 'toPrism'))->toBeTrue()
        ->and(method_exists($conversation, 'addPrismResponse'))->toBeTrue()
        ->and(method_exists($conversation, 'streamPrismResponse'))->toBeTrue();
});

it('can use prism methods on conversations created through the trait', function () {
    $conversation = $this->user->startConversation([
        'title' => 'Test Conversation',
    ]);

    // Add some messages
    $conversation->addUserMessage('Hello');
    $conversation->addAssistantMessage('Hi there!');

    // Should be able to convert to Prism format
    $prismMessages = $conversation->toPrism();

    expect($prismMessages)->toBeArray()
        ->toHaveCount(2)
        ->and($prismMessages[0])->toBeInstanceOf(UserMessage::class)
        ->and($prismMessages[0]->content)->toBe('Hello')
        ->and($prismMessages[1])->toBeInstanceOf(AssistantMessage::class)
        ->and($prismMessages[1]->content)->toBe('Hi there!');
});

it('returns prism-enabled conversations from conversations() relationship', function () {
    // Create multiple conversations
    $this->user->startConversation(['title' => 'Chat 1']);
    $this->user->startConversation(['title' => 'Chat 2']);

    $conversations = $this->user->conversations;

    expect($conversations)->toHaveCount(2);

    // Each conversation should be our extended model with Prism methods
    foreach ($conversations as $conversation) {
        expect($conversation)->toBeInstanceOf(Conversation::class)
            ->and(method_exists($conversation, 'toPrism'))->toBeTrue();
    }
});

it('maintains all original converse functionality', function () {
    $conversation = $this->user->startConversation([
        'title' => 'Test',
        'metadata' => ['custom' => 'data'],
    ]);

    // All original methods should work
    $userMessage = $conversation->addUserMessage('Test message');
    $assistantMessage = $conversation->addAssistantMessage('Response');

    expect($conversation->messages)->toHaveCount(2)
        ->and($conversation->title)->toBe('Test')
        ->and($conversation->metadata)->toMatchArray(['custom' => 'data'])
        ->and($userMessage->role->value)->toBe('user')
        ->and($assistantMessage->role->value)->toBe('assistant');
});
