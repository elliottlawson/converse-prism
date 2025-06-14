<?php

namespace ElliottLawson\ConversePrism\Concerns;

use ElliottLawson\ConversePrism\Support\PrismFormatter;
use ElliottLawson\ConversePrism\Support\PrismRequestBuilder;
use ElliottLawson\ConversePrism\Support\PrismStream;

trait InteractsWithPrism
{
    /**
     * Convert conversation messages or single message to Prism format
     *
     * @return array|PrismMessage When called on Conversation, returns array of messages. When called on Message, returns single message.
     */
    public function toPrism(): mixed
    {
        // If called on Conversation model
        if (method_exists($this, 'messages')) {
            return $this->messages()
                ->completed()
                ->orderBy('created_at')
                ->get()
                ->map(fn ($message) => PrismFormatter::formatMessage($message))
                ->toArray();
        }

        // If called on Message model
        return PrismFormatter::formatMessage($this);
    }

    /**
     * Convert single message to Prism format (alias for toPrism when called on Message)
     * This method is designed for method chaining: $conversation->addUserMessage(...)->toPrismMessage()
     *
     * @return PrismMessage
     */
    public function toPrismMessage()
    {
        // Only available on Message model
        if (method_exists($this, 'messages')) {
            throw new \BadMethodCallException('toPrismMessage can only be called on Message model. Use toPrism() on Conversation model instead.');
        }

        return PrismFormatter::formatMessage($this);
    }

    /**
     * Add a Prism response to the conversation
     *
     * @return \ElliottLawson\Converse\Models\Message
     */
    public function addPrismResponse($response, array $metadata = [])
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('addPrismResponse can only be called on Conversation model');
        }

        // Handle tool calls
        if (isset($response->toolCalls) && filled($response->toolCalls)) {
            return $this->addToolCallMessage(
                json_encode($response->toolCalls),
                $this->extractPrismMetadata($response, $metadata)
            );
        }

        // Handle tool results
        if (isset($response->toolResults) && filled($response->toolResults)) {
            return $this->addToolResultMessage(
                json_encode($response->toolResults),
                $this->extractPrismMetadata($response, $metadata)
            );
        }

        // Standard assistant message
        return $this->addAssistantMessage(
            $response->text ?? '',
            $this->extractPrismMetadata($response, $metadata)
        );
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
     * Fluent interface: Add a system message and return conversation for chaining
     */
    public function withSystemMessage(string $content, array $metadata = []): self
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('withSystemMessage can only be called on Conversation model');
        }

        $this->addSystemMessage($content, $metadata);
        return $this;
    }

    /**
     * Fluent interface: Add a user message and return conversation for chaining
     */
    public function withUserMessage(string $content, array $metadata = []): self
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('withUserMessage can only be called on Conversation model');
        }

        $this->addUserMessage($content, $metadata);
        return $this;
    }

    /**
     * Fluent interface: Add an assistant message and return conversation for chaining
     */
    public function withAssistantMessage(string $content, array $metadata = []): self
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('withAssistantMessage can only be called on Conversation model');
        }

        $this->addAssistantMessage($content, $metadata);
        return $this;
    }

    /**
     * Fluent interface: Start a Prism request with the conversation messages
     */
    public function sendToPrism(): PrismRequestBuilder
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('sendToPrism can only be called on Conversation model');
        }

        return new PrismRequestBuilder($this);
    }

}
