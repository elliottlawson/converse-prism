# Converse Prism Integration Tests

This test suite validates the integration between Converse and Prism PHP.

## Test Structure

### Unit Tests (`tests/Unit/`)

**MessageFormattingTest.php**
- Tests the `PrismFormatter` class that converts Converse messages to Prism format
- Verifies role mapping (user → user, assistant → assistant, etc.)
- Tests tool call and tool result formatting
- Handles edge cases like null content and malformed JSON
- No database or Laravel required - pure unit tests

### Feature Tests (`tests/Feature/`)

**ConversationPrismMethodsTest.php**
- Tests the methods added to Conversation and Message models:
  - `toPrism()` - Converts messages to Prism format
  - `addPrismResponse()` - Saves Prism responses as Converse messages
  - `streamPrismResponse()` - Creates streaming response handler
- Verifies that these methods work correctly on the models
- Tests error handling (e.g., calling conversation methods on message model)

**PrismResponseHandlingTest.php**
- Tests handling of various Prism response types:
  - Standard text responses with metadata
  - Tool call responses
  - Complex conversations with mixed message types
  - Streaming responses with chunk management
  - Multi-step responses
  - Edge cases (empty responses, missing metadata)
- Uses mock Prism response objects to simulate real responses

## Running Tests

```bash
# Run all tests
composer test

# Run only unit tests
composer test -- --testsuite=Unit

# Run only feature tests
composer test -- --testsuite=Feature

# Run a specific test file
composer test -- tests/Unit/MessageFormattingTest.php
```

## Test Coverage

The tests validate:
1. **Message Formatting** - Converse messages correctly convert to Prism's expected format
2. **Response Handling** - Prism responses are properly saved with all metadata
3. **Streaming Support** - Streaming responses work with chunk tracking
4. **Tool Integration** - Tool calls and results are handled correctly
5. **Error Handling** - Appropriate exceptions for invalid operations

## Test Data

Tests use:
- `TestUser` model from the Converse package (`ElliottLawson\Converse\Tests\Models\TestUser`)
- Mock Prism response objects that simulate real API responses
- In-memory SQLite database for fast test execution

Note: The TestUser model is provided by the converse package (v0.1.1+) to ensure consistency across test suites.