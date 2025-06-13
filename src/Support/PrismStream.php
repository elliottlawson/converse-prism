<?php

namespace ElliottLawson\ConversePrism\Support;

use ElliottLawson\Converse\Models\Conversation;
use ElliottLawson\Converse\Models\Message;

class PrismStream
{
    protected Message $message;

    protected int $chunkCount = 0;

    protected float $startTime;

    public function __construct(
        protected Conversation $conversation,
        protected array $metadata = []
    ) {
        $this->startTime = microtime(true);
        $this->message = $conversation->startStreamingAssistant($metadata);
    }

    /**
     * Append a chunk to the streaming message
     */
    public function append(string $chunk): self
    {
        $this->message->appendChunk($chunk);
        $this->chunkCount++;

        return $this;
    }

    /**
     * Complete the streaming response
     */
    public function complete($prismResponse = null): Message
    {
        $duration = round((microtime(true) - $this->startTime) * 1000);

        $finalMetadata = array_merge($this->metadata, [
            'streamed' => true,
            'chunks' => $this->chunkCount,
            'stream_duration_ms' => $duration,
        ]);

        // If we have a Prism response object, extract its metadata
        if ($prismResponse) {
            $finalMetadata = array_merge(
                $finalMetadata,
                $this->extractPrismMetadata($prismResponse)
            );
        }

        return $this->message->completeStreaming($finalMetadata);
    }

    /**
     * Fail the streaming response
     */
    public function fail(string $error, array $errorMetadata = []): Message
    {
        $duration = round((microtime(true) - $this->startTime) * 1000);

        return $this->message->failStreaming($error, array_merge([
            'chunks_received' => $this->chunkCount,
            'stream_duration_ms' => $duration,
        ], $errorMetadata));
    }

    /**
     * Get the underlying message
     */
    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * Extract metadata from Prism response (same as trait method)
     */
    protected function extractPrismMetadata($response): array
    {
        $metadata = [];

        if (isset($response->usage)) {
            $metadata['tokens'] = $response->usage->totalTokens ?? null;
            $metadata['prompt_tokens'] = $response->usage->promptTokens ?? null;
            $metadata['completion_tokens'] = $response->usage->completionTokens ?? null;
        }

        if (isset($response->meta)) {
            $metadata['model'] = $response->meta->model ?? null;
            $metadata['provider_request_id'] = $response->meta->id ?? null;
        }

        if (isset($response->finishReason)) {
            $metadata['finish_reason'] = $response->finishReason->name;
        }

        return $metadata;
    }
}
