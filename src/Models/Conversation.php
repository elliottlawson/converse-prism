<?php

namespace ElliottLawson\ConversePrism\Models;

use ElliottLawson\Converse\Models\Conversation as BaseConversation;
use ElliottLawson\ConversePrism\Concerns\InteractsWithPrism;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends BaseConversation
{
    use InteractsWithPrism;

    /**
     * Override the messages relationship to use our extended Message model
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Override the lastMessage relationship to use our extended Message model
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
