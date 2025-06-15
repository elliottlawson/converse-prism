# Setup

After installation, you need to configure your User model.

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