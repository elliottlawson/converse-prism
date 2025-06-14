<?php

/**
 * Unit Tests: Message Formatting
 *
 * These tests verify that Converse messages are correctly formatted
 * for Prism's expected message structure. No database or application
 * bootstrapping is required.
 */

use ElliottLawson\Converse\Enums\MessageRole;
use ElliottLawson\Converse\Models\Message;
use ElliottLawson\ConversePrism\Support\PrismFormatter;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

it('formats messages to correct Prism message types', function ($role, $expectedClass) {
    $message = new Message([
        'role' => $role,
        'content' => 'Test content',
    ]);

    $formatted = PrismFormatter::formatMessage($message);

    expect($formatted)->toBeInstanceOf($expectedClass);

    // Tool calls and tool results handle content differently
    if ($role === MessageRole::ToolCall) {
        expect($formatted->content)->toBe('');
    } elseif ($role === MessageRole::ToolResult) {
        // Tool results don't have a content property
        expect($formatted->toolResults)->toBeArray();
    } else {
        expect($formatted->content)->toBe('Test content');
    }
})->with([
    'user' => [MessageRole::User, UserMessage::class],
    'assistant' => [MessageRole::Assistant, AssistantMessage::class],
    'system' => [MessageRole::System, SystemMessage::class],
    'tool call' => [MessageRole::ToolCall, AssistantMessage::class],
    'tool result' => [MessageRole::ToolResult, ToolResultMessage::class],
]);

it('handles null content gracefully', function () {
    $message = new Message([
        'role' => MessageRole::User,
        'content' => null,
    ]);

    $formatted = PrismFormatter::formatMessage($message);

    expect($formatted)->toBeInstanceOf(UserMessage::class)
        ->and($formatted->content)->toBe('');
});

it('handles tool calls with proper JSON', function () {
    $toolCalls = [
        [
            'id' => 'call_123',
            'name' => 'get_weather',
            'arguments' => ['location' => 'Paris'],
        ],
    ];

    $message = new Message([
        'role' => MessageRole::ToolCall,
        'content' => json_encode($toolCalls),
    ]);

    $formatted = PrismFormatter::formatMessage($message);

    expect($formatted)->toBeInstanceOf(AssistantMessage::class)
        ->and($formatted->content)->toBe('')
        ->and($formatted->toolCalls)->toHaveCount(1)
        ->and($formatted->toolCalls[0]->id)->toBe('call_123')
        ->and($formatted->toolCalls[0]->name)->toBe('get_weather');
});

it('handles tool results with proper JSON', function () {
    $toolResults = [
        [
            'id' => 'call_123',
            'result' => 'Sunny, 22°C',
        ],
    ];

    $message = new Message([
        'role' => MessageRole::ToolResult,
        'content' => json_encode($toolResults),
    ]);

    $formatted = PrismFormatter::formatMessage($message);

    expect($formatted)->toBeInstanceOf(ToolResultMessage::class)
        ->and($formatted->toolResults)->toHaveCount(1)
        ->and($formatted->toolResults[0]->toolCallId)->toBe('call_123')
        ->and($formatted->toolResults[0]->result)->toBe('Sunny, 22°C');
});

describe('PrismFormatter Helper Methods', function () {
    it('creates user messages', function () {
        $message = PrismFormatter::createUserMessage('Hello world');
        
        expect($message)->toBeInstanceOf(UserMessage::class)
            ->and($message->content)->toBe('Hello world');
    });

    it('creates system messages', function () {
        $message = PrismFormatter::createSystemMessage('You are a helpful assistant');
        
        expect($message)->toBeInstanceOf(SystemMessage::class)
            ->and($message->content)->toBe('You are a helpful assistant');
    });

    it('creates assistant messages without tool calls', function () {
        $message = PrismFormatter::createAssistantMessage('I can help with that');
        
        expect($message)->toBeInstanceOf(AssistantMessage::class)
            ->and($message->content)->toBe('I can help with that')
            ->and($message->toolCalls)->toBeEmpty();
    });

    it('creates assistant messages with tool calls from arrays', function () {
        $toolCalls = [
            [
                'id' => 'call_123',
                'name' => 'get_weather',
                'arguments' => ['location' => 'Paris']
            ]
        ];
        
        $message = PrismFormatter::createAssistantMessage('Checking weather...', $toolCalls);
        
        expect($message)->toBeInstanceOf(AssistantMessage::class)
            ->and($message->content)->toBe('Checking weather...')
            ->and($message->toolCalls)->toHaveCount(1)
            ->and($message->toolCalls[0]->id)->toBe('call_123')
            ->and($message->toolCalls[0]->name)->toBe('get_weather');
    });

    it('creates tool result messages from arrays', function () {
        $results = [
            [
                'toolCallId' => 'call_123',
                'toolName' => 'get_weather',
                'args' => ['location' => 'Paris'],
                'result' => 'Sunny, 22°C'
            ]
        ];
        
        $message = PrismFormatter::createToolResultMessage($results);
        
        expect($message)->toBeInstanceOf(ToolResultMessage::class)
            ->and($message->toolResults)->toHaveCount(1)
            ->and($message->toolResults[0]->toolCallId)->toBe('call_123')
            ->and($message->toolResults[0]->toolName)->toBe('get_weather')
            ->and($message->toolResults[0]->result)->toBe('Sunny, 22°C');
    });

    it('handles alternative array key formats for tool results', function () {
        $results = [
            [
                'id' => 'call_456',
                'name' => 'calculate',
                'arguments' => ['expression' => '2+2'],
                'result' => '4'
            ]
        ];
        
        $message = PrismFormatter::createToolResultMessage($results);
        
        expect($message)->toBeInstanceOf(ToolResultMessage::class)
            ->and($message->toolResults)->toHaveCount(1)
            ->and($message->toolResults[0]->toolCallId)->toBe('call_456')
            ->and($message->toolResults[0]->toolName)->toBe('calculate')
            ->and($message->toolResults[0]->result)->toBe('4');
    });
});
