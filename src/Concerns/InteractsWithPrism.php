<?php

namespace ElliottLawson\ConversePrism\Concerns;

use ElliottLawson\ConversePrism\Support\PrismFormatter;
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
     * Add a user message and return its Prism representation
     */
    public function addUserMessageToPrism(string $content, array $metadata = [])
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('addUserMessageToPrism can only be called on Conversation model');
        }

        $message = $this->addUserMessage($content, $metadata);
        return PrismFormatter::formatMessage($message);
    }

    /**
     * Add a system message and return its Prism representation
     */
    public function addSystemMessageToPrism(string $content, array $metadata = [])
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('addSystemMessageToPrism can only be called on Conversation model');
        }

        $message = $this->addSystemMessage($content, $metadata);
        return PrismFormatter::formatMessage($message);
    }

    /**
     * Add an assistant message and return its Prism representation
     */
    public function addAssistantMessageToPrism(string $content, array $metadata = [])
    {
        // Only available on Conversation model
        if (! method_exists($this, 'messages')) {
            throw new \BadMethodCallException('addAssistantMessageToPrism can only be called on Conversation model');
        }

        $message = $this->addAssistantMessage($content, $metadata);
        return PrismFormatter::formatMessage($message);
    }
}
