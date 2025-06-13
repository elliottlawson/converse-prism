<?php

namespace ElliottLawson\ConversePrism\Concerns;

use ElliottLawson\Converse\Traits\HasAIConversations as BaseHasAIConversations;
use ElliottLawson\ConversePrism\Models\Conversation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAIConversations
{
    use BaseHasAIConversations {
        startConversation as baseStartConversation;
        conversations as baseConversations;
    }

    /**
     * Start a new conversation with Prism support
     */
    public function startConversation(array $attributes = []): Conversation
    {
        $conversation = $this->baseStartConversation($attributes);

        return Conversation::find($conversation->id);
    }

    /**
     * Override the conversations relationship to use our extended model
     */
    public function conversations(): MorphMany
    {
        return $this->morphMany(Conversation::class, 'conversable');
    }
}
