<?php

namespace ElliottLawson\ConversePrism\Support;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Text\Response;

class PrismRequestBuilder
{
    protected $conversation;
    protected $pendingRequest;

    public function __construct($conversation)
    {
        $this->conversation = $conversation;
        $this->pendingRequest = Prism::text()->withMessages($conversation->toPrism());
    }

    /**
     * Configure the provider and model to use
     */
    public function using(Provider|string $provider, string $model): self
    {
        $this->pendingRequest->using($provider, $model);
        return $this;
    }

    /**
     * Set the maximum number of tokens
     */
    public function withMaxTokens(?int $maxTokens): self
    {
        $this->pendingRequest->withMaxTokens($maxTokens);
        return $this;
    }

    /**
     * Set the temperature for response generation
     */
    public function usingTemperature(int|float|null $temperature): self
    {
        $this->pendingRequest->usingTemperature($temperature);
        return $this;
    }

    /**
     * Set the top-p value for response generation
     */
    public function usingTopP(int|float $topP): self
    {
        $this->pendingRequest->usingTopP($topP);
        return $this;
    }

    /**
     * Set the maximum number of steps for multi-step responses
     */
    public function withMaxSteps(int $steps): self
    {
        $this->pendingRequest->withMaxSteps($steps);
        return $this;
    }

    /**
     * Execute the request and add the response to the conversation
     */
    public function execute(): Response
    {
        $response = $this->pendingRequest->asText();
        
        // Add the response to the conversation
        $this->conversation->addPrismResponse($response);
        
        return $response;
    }

    /**
     * Execute the request as a stream and return a PrismStream
     */
    public function stream(): \ElliottLawson\ConversePrism\Support\PrismStream
    {
        $prismStream = $this->conversation->streamPrismResponse();
        
        foreach ($this->pendingRequest->asStream() as $chunk) {
            $prismStream->append($chunk->text ?? '');
        }
        
        $prismStream->complete();
        
        return $prismStream;
    }

    /**
     * Get access to the underlying Prism PendingRequest for advanced configuration
     */
    public function prismRequest()
    {
        return $this->pendingRequest;
    }
}