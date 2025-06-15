# API Reference

Complete reference for all Converse Prism methods and classes.

## Overview

Converse Prism extends Laravel Converse with seamless Prism PHP integration. This API reference covers the additional methods and classes provided by this package.

## Main Components

### [Conversation Methods](conversations.md)
Extensions to the Conversation model for Prism integration:
- `toPrismText()` - Generate text completions
- `toPrismStructured()` - Generate structured outputs
- `toPrismEmbeddings()` - Generate embeddings
- `addPrismResponse()` - Store AI responses with metadata
- `streamPrismResponse()` - Handle streaming responses

### [Message Methods](messages.md)
Extensions to the Message model:
- `toPrismMessage()` - Convert messages to Prism format

### [PrismStream](prism-stream.md)
The streaming handler class:
- `append()` - Add chunks to the stream
- `complete()` - Finalize the stream
- `fail()` - Handle stream failures

### [Metadata](metadata.md)
Automatic metadata extraction and structure for AI responses.

## Inherited Functionality

All standard Laravel Converse methods remain available. See the [Converse documentation](https://converse-php.netlify.app/api/conversations.html) for the complete API reference of the base package.

## Version Compatibility

- **PHP:** 8.2+
- **Laravel:** 11.0+ or 12.0+
- **Converse:** ^0.1
- **Prism PHP:** ^0.71 