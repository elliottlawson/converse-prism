<?php

namespace ElliottLawson\ConversePrism\Models;

use ElliottLawson\Converse\Models\Conversation as BaseConversation;
use ElliottLawson\ConversePrism\Concerns\InteractsWithPrism;

class Conversation extends BaseConversation
{
    use InteractsWithPrism;
}
