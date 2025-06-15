# Setup

After installation, you need to configure your User model and set up your AI provider API keys.

## Configure Your User Model

Replace the standard Converse trait with the Converse Prism trait in your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ElliottLawson\ConversePrism\Concerns\HasAIConversations;

class User extends Authenticatable
{
    use HasAIConversations;
    
    // Rest of your user model...
}
```

That's it! Your User model is now ready to manage AI conversations.

## Configure AI Provider API Keys

Add your AI provider API keys to your `.env` file:

```bash
# OpenAI
OPENAI_API_KEY=your-openai-api-key

# Anthropic
ANTHROPIC_API_KEY=your-anthropic-api-key

# Google
GEMINI_API_KEY=your-gemini-api-key

# Groq (optional)
GROQ_API_KEY=your-groq-api-key
```

You only need to add API keys for the providers you plan to use.

## Publishing Configuration (Optional)

If you want to customize the default settings, you can publish the configuration files:

### Prism Configuration

```bash
php artisan vendor:publish --tag=prism-config
```

### Converse Configuration

```bash
php artisan vendor:publish --tag=converse-config
```

## Next Steps

- If you're migrating from Converse, see [Migration from Converse](migration.md)
- Learn about [Basic Usage](basic-usage.md) patterns
- Explore [Streaming](streaming.md) for real-time responses 