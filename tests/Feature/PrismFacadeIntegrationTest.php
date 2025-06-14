<?php

/**
 * Feature Tests: Prism Facade Integration
 *
 * These tests verify that conversations can be seamlessly converted
 * to Prism facade objects (PendingTextRequest, PendingStructuredRequest, etc.)
 * with messages pre-populated and full API access.
 */

use ElliottLawson\ConversePrism\Models\Conversation;
use ElliottLawson\ConversePrism\Models\Message;
use ElliottLawson\ConversePrism\Tests\Models\TestUser;
use Prism\Prism\Embeddings\PendingRequest as PendingEmbeddingRequest;
use Prism\Prism\Structured\PendingRequest as PendingStructuredRequest;
use Prism\Prism\Text\PendingRequest as PendingTextRequest;

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

describe('Prism Text Integration', function () {
    it('converts conversation to PendingTextRequest with fluent chaining', function () {
        $pendingRequest = $this->conversation
            ->addSystemMessage('You are a helpful coding assistant')
            ->addUserMessage('Write a Python function to calculate fibonacci')
            ->toPrismText()
            ->withMaxTokens(500)
            ->usingTemperature(0.3);

        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);

        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'asText'))->toBeTrue();
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
    });

    it('demonstrates seamless text generation configuration', function () {
        $pendingRequest = $this->conversation
            ->addSystemMessage('You are a creative writer')
            ->addUserMessage('Write a short poem about artificial intelligence')
            ->toPrismText()
            ->withMaxTokens(200)
            ->usingTemperature(0.8)
            ->usingTopP(0.9);

        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);
        expect(method_exists($pendingRequest, 'asText'))->toBeTrue();
        expect(method_exists($pendingRequest, 'asStream'))->toBeTrue();
    });

    it('can be used with fluent message building and provider selection', function () {
        $pendingRequest = $this->conversation
            ->addSystemMessage('You are a helpful assistant')
            ->addUserMessage('Write a haiku about coding')
            ->toPrismText()
            ->withMaxTokens(50);

        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);

        // Verify messages were added to conversation
        $messages = $this->conversation->messages()->orderBy('created_at')->get();
        expect($messages)->toHaveCount(2);
        expect($messages[0]->role->value)->toBe('system');
        expect($messages[1]->role->value)->toBe('user');
    });
});

describe('Prism Structured Integration', function () {
    it('demonstrates structured output with fluent chaining', function () {
        $pendingRequest = $this->conversation
            ->addSystemMessage('You are a data analysis assistant')
            ->addUserMessage('Generate a JSON response with user profile data')
            ->toPrismStructured()
            ->withMaxTokens(300);

        expect($pendingRequest)->toBeInstanceOf(PendingStructuredRequest::class);

        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
        expect(method_exists($pendingRequest, 'withSchema'))->toBeTrue();
    });

    it('shows complex structured request configuration', function () {
        $pendingRequest = $this->conversation
            ->addSystemMessage('You extract structured data from text')
            ->addUserMessage('Extract key information from this product description')
            ->toPrismStructured()
            ->withMaxTokens(400)
            ->usingTemperature(0.1);

        expect($pendingRequest)->toBeInstanceOf(PendingStructuredRequest::class);
        expect(method_exists($pendingRequest, 'asStructured'))->toBeTrue();
    });
});

describe('Prism Embeddings Integration', function () {
    it('demonstrates embeddings generation with fluent chaining', function () {
        $pendingRequest = $this->conversation
            ->addUserMessage('First message')
            ->addSystemMessage('System response')
            ->addUserMessage('Document content for semantic search embedding')
            ->toPrismEmbeddings();

        expect($pendingRequest)->toBeInstanceOf(PendingEmbeddingRequest::class);

        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'fromInput'))->toBeTrue();
        expect(method_exists($pendingRequest, 'asEmbeddings'))->toBeTrue();
    });

    it('shows embeddings with additional input chaining', function () {
        $pendingRequest = $this->conversation
            ->addSystemMessage('Processing document for vector database')
            ->addUserMessage('Main document content to embed')
            ->toPrismEmbeddings()
            ->fromInput('Additional metadata text');

        expect($pendingRequest)->toBeInstanceOf(PendingEmbeddingRequest::class);
    });
});

describe('Error Handling', function () {
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

describe('Integration with Other Features', function () {
    it('demonstrates both direct facade and custom builder approaches', function () {
        // Method 1: Direct Prism facade access with chaining
        $prismRequest = $this->conversation
            ->addSystemMessage('You are a helpful assistant')
            ->addUserMessage('Hello, how can you help?')
            ->toPrismText()
            ->withMaxTokens(100);
        expect($prismRequest)->toBeInstanceOf(PendingTextRequest::class);

        // Method 2: Our custom builder approach
        $builderRequest = $this->conversation
            ->addUserMessage('Follow-up question')
            ->toPrismText()
            ->withMaxTokens(150);
        expect($builderRequest)->toBeInstanceOf(PendingTextRequest::class);
    });

    it('maintains conversation state across chained operations', function () {
        // Convert to Prism facade with chaining
        $prismRequest = $this->conversation
            ->addSystemMessage('You are helpful')
            ->addUserMessage('Question 1')
            ->toPrismText()
            ->withMaxTokens(200);

        // Add more messages and create new request
        $newPrismRequest = $this->conversation
            ->addUserMessage('Question 2')
            ->toPrismText()
            ->usingTemperature(0.7);

        // Verify state is maintained
        $messages = $this->conversation->messages()->orderBy('created_at')->get();
        expect($messages)->toHaveCount(3); // system + user1 + user2

        // Both requests should be valid
        expect($newPrismRequest)->toBeInstanceOf(PendingTextRequest::class);
    });
});
