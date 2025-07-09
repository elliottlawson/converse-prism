<?php

namespace ElliottLawson\ConversePrism\Concerns;

use ElliottLawson\ConversePrism\Support\PrismFormatter;
use ElliottLawson\ConversePrism\Support\PrismStream;
use Prism\Prism\Contracts\Message as PrismMessage;
use Prism\Prism\Embeddings\PendingRequest as PendingEmbeddingRequest;
use Prism\Prism\Prism;
use Prism\Prism\Structured\PendingRequest as PendingStructuredRequest;
use Prism\Prism\Text\PendingRequest as PendingTextRequest;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

trait InteractsWithPrism
{
    /**
     * Convert conversation messages to Prism format
     *
     * @return array Array of Prism message objects
     */
    public function toPrismMessages(): array
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('toPrismMessages can only be called on Conversation model');
        }

        return $this->messages()
            ->completed()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($message) => PrismFormatter::formatMessage($message))
            ->toArray();
    }

    /**
     * Convert single message to Prism format
     */
    public function toPrismMessage(): PrismMessage
    {
        // Only available on Message model
        if (method_exists($this, 'messages')) {
            throw new \BadMethodCallException('toPrismMessage can only be called on Message model. Use toPrismMessages() on Conversation model instead.');
        }

        return PrismFormatter::formatMessage($this);
    }

    /**
     * Add a Prism response to the conversation
     *
     * @return self Returns the conversation for fluent chaining
     */
    public function addPrismResponse($response, array $metadata = []): self
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('addPrismResponse can only be called on Conversation model');
        }

        // Extract metadata first (this includes token counts, etc.)
        $extractedMetadata = $this->extractPrismMetadata($response, $metadata);

        // Clean metadata to remove any cache control that might have leaked through
        $this->cleanMetadataForStorage($extractedMetadata);

        // Handle tool calls
        if (isset($response->toolCalls) && filled($response->toolCalls)) {
            $this->addToolCallMessage(
                json_encode($response->toolCalls),
                $extractedMetadata
            );

            return $this;
        }

        // Handle tool results
        if (isset($response->toolResults) && filled($response->toolResults)) {
            $this->addToolResultMessage(
                json_encode($response->toolResults),
                $extractedMetadata
            );

            return $this;
        }

        // Standard assistant message
        $this->addAssistantMessage(
            $response->text ?? '',
            $extractedMetadata
        );

        return $this;
    }

    /**
     * Start streaming a Prism response
     */
    public function streamPrismResponse(array $metadata = []): PrismStream
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('streamPrismResponse can only be called on Conversation model');
        }

        return new PrismStream($this, $metadata);
    }

    /**
     * Extract metadata from Prism response
     */
    protected function extractPrismMetadata($response, array $additional = []): array
    {
        $metadata = [];

        // Extract usage data
        if (isset($response->usage)) {
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $metadata['tokens'] = $promptTokens + $completionTokens;
            $metadata['prompt_tokens'] = $promptTokens ?: null;
            $metadata['completion_tokens'] = $completionTokens ?: null;
        }

        // Extract response metadata
        if (isset($response->meta)) {
            $metadata['model'] = $response->meta->model ?? null;
            $metadata['provider_request_id'] = $response->meta->id ?? null;
        }

        // Extract finish reason
        if (isset($response->finishReason)) {
            $metadata['finish_reason'] = $response->finishReason?->name;
        }

        // Extract steps for multi-step responses
        if (isset($response->steps) && count($response->steps) > 0) {
            $metadata['steps'] = count($response->steps);
        }

        return array_merge($metadata, $additional);
    }

    /**
     * Clean metadata to remove transient provider options before storage
     */
    protected function cleanMetadataForStorage(array &$metadata): void
    {
        // Remove any cache control related metadata that shouldn't be persisted
        unset($metadata['cache_type']);
        unset($metadata['cache_control']);
        unset($metadata['provider_options']);
    }

    /**
     * Convert conversation to a Prism text request with messages pre-populated
     */
    public function toPrismText(): PendingTextRequest
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('toPrismText can only be called on Conversation model');
        }

        $prismMessages = collect($this->toPrismMessages());

        // Separate system messages from other messages
        $systemMessages = $prismMessages->filter(fn ($message) => $message instanceof SystemMessage);
        $otherMessages = $prismMessages->reject(fn ($message) => $message instanceof SystemMessage);

        $prismRequest = Prism::text();

        // Add system messages individually to preserve them as separate prompts
        if ($systemMessages->isNotEmpty()) {
            $prismRequest = $prismRequest->withSystemPrompts($systemMessages->values()->all());
        }

        // Add other messages
        if ($otherMessages->isNotEmpty()) {
            $prismRequest = $prismRequest->withMessages($otherMessages->values()->all());
        }

        return $prismRequest;
    }

    /**
     * Convert conversation to a Prism structured request with messages pre-populated
     */
    public function toPrismStructured(): PendingStructuredRequest
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('toPrismStructured can only be called on Conversation model');
        }

        $prismMessages = collect($this->toPrismMessages());

        // Separate system messages from other messages
        $systemMessages = $prismMessages->filter(fn ($message) => $message instanceof SystemMessage);
        $otherMessages = $prismMessages->reject(fn ($message) => $message instanceof SystemMessage);

        $prismRequest = Prism::structured();

        // Add system messages individually to preserve them as separate prompts
        if ($systemMessages->isNotEmpty()) {
            $prismRequest = $prismRequest->withSystemPrompts($systemMessages->values()->all());
        }

        // Add other messages
        if ($otherMessages->isNotEmpty()) {
            $prismRequest = $prismRequest->withMessages($otherMessages->values()->all());
        }

        return $prismRequest;
    }

    /**
     * Convert conversation to a Prism embeddings request
     */
    public function toPrismEmbeddings(): PendingEmbeddingRequest
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('toPrismEmbeddings can only be called on Conversation model');
        }

        // For embeddings, we'll use the last user message content as the input
        $lastUserMessage = $this->messages()
            ->where('role', \ElliottLawson\Converse\Enums\MessageRole::User)
            ->latest()
            ->first();

        $input = $lastUserMessage ? $lastUserMessage->content : '';

        return Prism::embeddings()->fromInput($input);
    }
}
