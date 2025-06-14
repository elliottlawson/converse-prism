<?php

namespace ElliottLawson\ConversePrism\Support;

use ElliottLawson\Converse\Enums\MessageRole;
use ElliottLawson\Converse\Models\Message;
use Prism\Prism\Contracts\Message as PrismMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;

class PrismFormatter
{
    /**
     * Format a Converse message to a Prism message object
     */
    public static function formatMessage(Message $message): PrismMessage
    {
        return match ($message->role) {
            MessageRole::User => new UserMessage($message->content ?? ''),
            MessageRole::System => new SystemMessage($message->content ?? ''),
            MessageRole::Assistant => new AssistantMessage($message->content ?? ''),
            MessageRole::ToolCall => self::createToolCallMessage($message),
            MessageRole::ToolResult => self::createToolResultMessageFromMessage($message),
        };
    }

    /**
     * Create an AssistantMessage with tool calls
     */
    private static function createToolCallMessage(Message $message): AssistantMessage
    {
        $toolCallsData = json_decode($message->content, true) ?: [];
        $toolCalls = [];

        foreach ($toolCallsData as $callData) {
            $toolCalls[] = new ToolCall(
                id: $callData['id'] ?? '',
                name: $callData['name'] ?? '',
                arguments: $callData['arguments'] ?? []
            );
        }

        return new AssistantMessage('', $toolCalls);
    }

    /**
     * Create a ToolResultMessage from a Converse Message
     */
    private static function createToolResultMessageFromMessage(Message $message): ToolResultMessage
    {
        $toolResultsData = json_decode($message->content, true) ?: [];
        $toolResults = [];

        foreach ($toolResultsData as $resultData) {
            $toolResults[] = new ToolResult(
                toolCallId: $resultData['id'] ?? '',
                toolName: '', // Not stored in Converse
                args: [], // Not stored in Converse
                result: $resultData['result'] ?? ''
            );
        }

        return new ToolResultMessage($toolResults);
    }
}
