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
            $metadata['tokens'] = $response->usage->totalTokens ?? null;
            $metadata['prompt_tokens'] = $response->usage->promptTokens ?? null;
            $metadata['completion_tokens'] = $response->usage->completionTokens ?? null;
        }

        // Extract response metadata
        if (isset($response->response)) {
            $metadata['model'] = $response->response['model'] ?? null;
            $metadata['provider_request_id'] = $response->response['id'] ?? null;
        }

        // Extract finish reason
        if (isset($response->finishReason)) {
            $metadata['finish_reason'] = is_object($response->finishReason) && method_exists($response->finishReason, 'value')
                ? $response->finishReason->value
                : $response->finishReason;
        }

        // Extract steps for multi-step responses
        if (isset($response->steps) && count($response->steps) > 0) {
            $metadata['steps'] = count($response->steps);
        }

        return array_merge($metadata, $additional);
    }
}
