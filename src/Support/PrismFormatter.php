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
     * Create a Prism UserMessage
     */
    public static function createUserMessage(string $content): UserMessage
    {
        return new UserMessage($content);
    }

    /**
     * Create a Prism SystemMessage
     */
    public static function createSystemMessage(string $content): SystemMessage
    {
        return new SystemMessage($content);
    }

    /**
     * Create a Prism AssistantMessage
     */
    public static function createAssistantMessage(string $content, array $toolCalls = []): AssistantMessage
    {
        $prismToolCalls = [];
        foreach ($toolCalls as $call) {
            if ($call instanceof ToolCall) {
                $prismToolCalls[] = $call;
            } elseif (is_array($call)) {
                $prismToolCalls[] = new ToolCall(
                    id: $call['id'] ?? '',
                    name: $call['name'] ?? '',
                    arguments: $call['arguments'] ?? []
                );
            }
        }

        return new AssistantMessage($content, $prismToolCalls);
    }

    /**
     * Create a Prism ToolResultMessage
     */
    public static function createToolResultMessage(array $results): ToolResultMessage
    {
        $prismResults = [];
        foreach ($results as $result) {
            if ($result instanceof ToolResult) {
                $prismResults[] = $result;
            } elseif (is_array($result)) {
                $prismResults[] = new ToolResult(
                    toolCallId: $result['toolCallId'] ?? $result['id'] ?? '',
                    toolName: $result['toolName'] ?? $result['name'] ?? '',
                    args: $result['args'] ?? $result['arguments'] ?? [],
                    result: $result['result'] ?? ''
                );
            }
        }

        return new ToolResultMessage($prismResults);
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
