<?php

namespace ElliottLawson\ConversePrism\Support;

use ElliottLawson\Converse\Models\Conversation;

class PrismRequestProxy
{
    protected $pendingRequest;

    protected $conversation;

    protected $metadata;

    public function __construct($pendingRequest, Conversation $conversation, array $metadata = [])
    {
        $this->pendingRequest = $pendingRequest;
        $this->conversation = $conversation;
        $this->metadata = $metadata;
    }

    /**
     * Proxy all method calls to the underlying PendingTextRequest
     */
    public function __call($method, $arguments)
    {
        $result = $this->pendingRequest->$method(...$arguments);

        // If the method returns the pending request, return the proxy instead
        if ($result === $this->pendingRequest) {
            return $this;
        }

        // Special handling for asText() to save to conversation
        if ($method === 'asText') {
            $this->conversation->addPrismResponse($result, $this->metadata);
        }

        return $result;
    }
}
