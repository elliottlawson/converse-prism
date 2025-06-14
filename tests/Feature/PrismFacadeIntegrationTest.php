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
use Prism\Prism\Text\PendingRequest as PendingTextRequest;
use Prism\Prism\Structured\PendingRequest as PendingStructuredRequest;
use Prism\Prism\Embeddings\PendingRequest as PendingEmbeddingRequest;

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
    it('converts conversation to PendingTextRequest with messages', function () {
        $this->conversation->addSystemMessage('You are helpful');
        $this->conversation->addUserMessage('Hello world');
        
        $pendingRequest = $this->conversation->toPrismText();
        
        expect($pendingRequest)->toBeInstanceOf(PendingTextRequest::class);
        
        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'asText'))->toBeTrue();
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
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

    it('can be used with fluent message building', function () {
        $pendingRequest = $this->conversation
            ->withSystemMessage('You are a helpful assistant')
            ->withUserMessage('Write a haiku about coding')
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
    it('converts conversation to PendingStructuredRequest with messages', function () {
        $this->conversation->addSystemMessage('You are helpful');
        $this->conversation->addUserMessage('Generate a JSON response');
        
        $pendingRequest = $this->conversation->toPrismStructured();
        
        expect($pendingRequest)->toBeInstanceOf(PendingStructuredRequest::class);
        
        // Verify that the PendingRequest has the correct methods available
        expect(method_exists($pendingRequest, 'using'))->toBeTrue();
        expect(method_exists($pendingRequest, 'withSchema'))->toBeTrue();
    });

    it('can be chained with schema configuration', function () {
        $pendingRequest = $this->conversation
            ->withUserMessage('Generate structured data')
            ->toPrismStructured()
            ->withMaxTokens(200);

        expect($pendingRequest)->toBeInstanceOf(PendingStructuredRequest::class);
        expect(method_exists($pendingRequest, 'asStructured'))->toBeTrue();
    });
});

describe('Prism Embeddings Integration', function () {
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

    it('can be chained with provider configuration', function () {
        $pendingRequest = $this->conversation
            ->withUserMessage('Text to embed')
            ->toPrismEmbeddings()
            ->fromInput('Additional text');

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
    it('works seamlessly with sendToPrism fluent interface', function () {
        // Show that both approaches can coexist
        $this->conversation->withUserMessage('Hello');
        
        // Method 1: Direct Prism facade access
        $prismRequest = $this->conversation->toPrismText();
        expect($prismRequest)->toBeInstanceOf(PendingTextRequest::class);
        
        // Method 2: Our custom builder
        $builderRequest = $this->conversation->sendToPrism();
        expect($builderRequest)->toBeInstanceOf(\ElliottLawson\ConversePrism\Support\PrismRequestBuilder::class);
    });

    it('maintains conversation state across different integrations', function () {
        $this->conversation
            ->withSystemMessage('You are helpful')
            ->withUserMessage('Question 1');
            
        // Convert to Prism facade
        $prismRequest = $this->conversation->toPrismText();
        
        // Add more messages
        $this->conversation->withUserMessage('Question 2');
        
        // Verify state is maintained
        $messages = $this->conversation->messages()->orderBy('created_at')->get();
        expect($messages)->toHaveCount(3);
        
        // New Prism request includes all messages
        $newPrismRequest = $this->conversation->toPrismText();
        expect($newPrismRequest)->toBeInstanceOf(PendingTextRequest::class);
    });
});