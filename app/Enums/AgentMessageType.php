<?php

namespace App\Enums;

enum AgentMessageType: string
{
    case Text = 'text';
    case PlanPreview = 'plan_preview';
    case FileDiff = 'file_diff';
    case ApprovalRequest = 'approval_request';
    case ExecutionUpdate = 'execution_update';
    case Error = 'error';
    case Clarification = 'clarification';
    case CodeContext = 'code_context';
    case SystemNotice = 'system_notice';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Message',
            self::PlanPreview => 'Execution Plan',
            self::FileDiff => 'File Changes',
            self::ApprovalRequest => 'Approval Required',
            self::ExecutionUpdate => 'Execution Progress',
            self::Error => 'Error',
            self::Clarification => 'Clarification Needed',
            self::CodeContext => 'Code Context',
            self::SystemNotice => 'System Notice',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'message-square',
            self::PlanPreview => 'clipboard-list',
            self::FileDiff => 'file-diff',
            self::ApprovalRequest => 'shield-check',
            self::ExecutionUpdate => 'activity',
            self::Error => 'alert-circle',
            self::Clarification => 'help-circle',
            self::CodeContext => 'code',
            self::SystemNotice => 'info',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Text => 'gray',
            self::PlanPreview => 'purple',
            self::FileDiff => 'blue',
            self::ApprovalRequest => 'amber',
            self::ExecutionUpdate => 'cyan',
            self::Error => 'red',
            self::Clarification => 'yellow',
            self::CodeContext => 'indigo',
            self::SystemNotice => 'slate',
        };
    }

    public function requiresAction(): bool
    {
        return in_array($this, [self::ApprovalRequest, self::Clarification]);
    }

    public function hasAttachments(): bool
    {
        return in_array($this, [self::PlanPreview, self::FileDiff, self::CodeContext]);
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
