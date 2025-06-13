<?php

namespace ElliottLawson\ConversePrism\Models;

use ElliottLawson\Converse\Models\Message as BaseMessage;
use ElliottLawson\ConversePrism\Concerns\InteractsWithPrism;

class Message extends BaseMessage
{
    use InteractsWithPrism;
}
