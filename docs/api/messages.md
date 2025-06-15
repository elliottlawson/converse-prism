# Messages API Reference

Extensions to the Message model for Prism PHP integration.

## Methods

### `toPrismMessage()`

Converts a single message to its Prism equivalent based on the message role.

```php
public function toPrismMessage(): mixed
```

**Returns:** Prism message object based on role:
- `system` → `\Prism\Prism\Messages\SystemMessage`
- `user` → `\Prism\Prism\Messages\UserMessage`
- `assistant` → `\Prism\Prism\Messages\AssistantMessage`
- `tool_call` → `\Prism\Prism\Messages\AssistantMessage` (with tool calls)
- `tool_result` → `\Prism\Prism\Messages\ToolResultMessage`

**Example:**
```php
$message = $conversation->messages()->first();
$prismMessage = $message->toPrismMessage();

// Returns appropriate Prism message type
if ($message->role === 'user') {
    // $prismMessage is UserMessage instance
}
```

## Message Roles

Converse Prism supports all standard message roles:

```php
const ROLES = [
    'system',        // System instructions
    'user',          // User input
    'assistant',     // AI response
    'tool_call',     // Tool invocation
    'tool_result'    // Tool response
];
```

## Inherited Properties

All standard Converse message properties remain available:

- `id` - Unique identifier
- `conversation_id` - Parent conversation ID
- `role` - Message role
- `content` - Message content
- `metadata` - JSON metadata field
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

See the [Converse documentation](https://converse-php.netlify.app/api/messages) for complete details on inherited properties and methods. 