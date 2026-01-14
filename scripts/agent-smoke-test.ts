/**
 * Claude Agent SDK - Laravel Project Scanner (Read-Only Smoke Test)
 *
 * This script uses the Claude Agent SDK to perform read-only verification
 * of a Laravel project structure using Glob, Grep, and Read tools.
 *
 * Prerequisites:
 *   1. Claude Code runtime installed (see installation commands below)
 *   2. npm install @anthropic-ai/claude-agent-sdk
 *   3. ANTHROPIC_API_KEY environment variable set
 *
 * Usage:
 *   npx tsx scripts/agent-smoke-test.ts [optional-repo-path]
 *
 * @example
 *   export ANTHROPIC_API_KEY="sk-ant-..."
 *   npx tsx scripts/agent-smoke-test.ts /path/to/laravel-repo
 */

import { query, type SDKMessage, type SDKSystemMessage, type SDKResultMessage } from '@anthropic-ai/claude-agent-sdk';
import * as path from 'path';
import * as fs from 'fs';

// ============================================================================
// Configuration
// ============================================================================

interface ScanConfig {
    repoPath: string;
    allowedTools: string[];
    permissionMode: 'bypassPermissions';
    maxTurns: number;
    model: string;
}

const DEFAULT_CONFIG: Omit<ScanConfig, 'repoPath'> = {
    allowedTools: ['Read', 'Glob', 'Grep'],
    permissionMode: 'bypassPermissions',
    maxTurns: 50,
    model: 'claude-sonnet-4-5-20250929',
};

// ============================================================================
// Prompt Definition
// ============================================================================

const SCAN_PROMPT = `You are scanning a Laravel project to verify its structure. Perform these tasks in order:

1. **GLOB**: Use Glob to discover project files. Search for these patterns:
   - "composer.json" (root config)
   - "routes/*.php" (route definitions)
   - "app/Http/Controllers/*.php" (controllers, limit 10)
   - "app/Models/*.php" (models, limit 10)
   - "config/*.php" (configuration files, limit 10)
   - "database/migrations/*.php" (migrations, limit 5)

2. **GREP**: Search for Laravel-specific patterns to confirm this is a Laravel project:
   - Pattern "Route::" in routes/ directory to find route definitions
   - Pattern "namespace App\\\\" to confirm App namespace usage
   - Pattern "use Illuminate\\\\" to find Laravel framework imports
   - Pattern "extends Model" in app/Models/ to find Eloquent models
   - Pattern "extends Controller" in app/Http/Controllers/ to find controllers

3. **READ**: Based on what you found, read the most relevant files:
   - Always read composer.json if it exists (to verify Laravel dependency)
   - Read routes/web.php or routes/api.php if they exist
   - Read one representative controller if found
   - Read one model file if found

4. **SUMMARIZE**: Provide a structured summary including:
   - Confirmation this is a Laravel project (yes/no with evidence)
   - Laravel version (from composer.json if available)
   - Key directories found
   - Number of controllers, models, migrations discovered
   - Main routes defined
   - Any interesting patterns or features detected

List all file paths as evidence for your findings.`;

// ============================================================================
// Message Type Guards
// ============================================================================

function isSystemInitMessage(msg: SDKMessage): msg is SDKSystemMessage {
    return msg.type === 'system' && (msg as SDKSystemMessage).subtype === 'init';
}

function isSuccessResultMessage(msg: SDKMessage): msg is SDKResultMessage & { subtype: 'success' } {
    return msg.type === 'result' && (msg as SDKResultMessage).subtype === 'success';
}

function isErrorResultMessage(msg: SDKMessage): msg is SDKResultMessage {
    return msg.type === 'result' && (msg as SDKResultMessage).subtype !== 'success';
}

function isAssistantMessage(msg: SDKMessage): boolean {
    return msg.type === 'assistant';
}

// ============================================================================
// Logging Utilities
// ============================================================================

const LOG_PREFIX = {
    INFO: '\x1b[36m[INFO]\x1b[0m',
    SUCCESS: '\x1b[32m[SUCCESS]\x1b[0m',
    ERROR: '\x1b[31m[ERROR]\x1b[0m',
    WARN: '\x1b[33m[WARN]\x1b[0m',
    DEBUG: '\x1b[90m[DEBUG]\x1b[0m',
};

function logInfo(message: string): void {
    console.log(`${LOG_PREFIX.INFO} ${message}`);
}

function logSuccess(message: string): void {
    console.log(`${LOG_PREFIX.SUCCESS} ${message}`);
}

function logError(message: string): void {
    console.error(`${LOG_PREFIX.ERROR} ${message}`);
}

function logWarn(message: string): void {
    console.warn(`${LOG_PREFIX.WARN} ${message}`);
}

function logDebug(message: string): void {
    if (process.env.DEBUG) {
        console.log(`${LOG_PREFIX.DEBUG} ${message}`);
    }
}

function logSeparator(): void {
    console.log('\n' + '='.repeat(80) + '\n');
}

// ============================================================================
// Validation
// ============================================================================

function validateEnvironment(): void {
    if (!process.env.ANTHROPIC_API_KEY) {
        logError('ANTHROPIC_API_KEY environment variable is not set.');
        logInfo('Set it with: export ANTHROPIC_API_KEY="sk-ant-..."');
        process.exit(1);
    }
    logInfo('ANTHROPIC_API_KEY is configured');
}

function validateRepoPath(repoPath: string): string {
    const absolutePath = path.resolve(repoPath);

    if (!fs.existsSync(absolutePath)) {
        logError(`Repository path does not exist: ${absolutePath}`);
        process.exit(1);
    }

    if (!fs.statSync(absolutePath).isDirectory()) {
        logError(`Path is not a directory: ${absolutePath}`);
        process.exit(1);
    }

    // Check for common Laravel indicators (optional, just warnings)
    const composerPath = path.join(absolutePath, 'composer.json');
    if (!fs.existsSync(composerPath)) {
        logWarn('composer.json not found - this might not be a Laravel project');
    }

    const artisanPath = path.join(absolutePath, 'artisan');
    if (!fs.existsSync(artisanPath)) {
        logWarn('artisan file not found - this might not be a Laravel project');
    }

    return absolutePath;
}

// ============================================================================
// SDK Interaction
// ============================================================================

interface ScanResult {
    success: boolean;
    sessionId: string | null;
    result: string | null;
    totalCostUsd: number;
    durationMs: number;
    numTurns: number;
    toolsUsed: string[];
    errors: string[];
}

async function runAgentScan(config: ScanConfig): Promise<ScanResult> {
    const result: ScanResult = {
        success: false,
        sessionId: null,
        result: null,
        totalCostUsd: 0,
        durationMs: 0,
        numTurns: 0,
        toolsUsed: [],
        errors: [],
    };

    const startTime = Date.now();

    try {
        logInfo(`Starting Claude Agent SDK scan...`);
        logInfo(`Working directory: ${config.repoPath}`);
        logInfo(`Allowed tools: ${config.allowedTools.join(', ')}`);
        logInfo(`Permission mode: ${config.permissionMode}`);
        logInfo(`Model: ${config.model}`);
        logSeparator();

        const agentQuery = query({
            prompt: SCAN_PROMPT,
            options: {
                cwd: config.repoPath,
                allowedTools: config.allowedTools,
                permissionMode: config.permissionMode,
                allowDangerouslySkipPermissions: true,
                maxTurns: config.maxTurns,
                model: config.model,
            },
        });

        let messageCount = 0;
        let toolCallCount = 0;

        for await (const message of agentQuery) {
            messageCount++;
            logDebug(`Message #${messageCount}: type=${message.type}`);

            // Handle system init message
            if (isSystemInitMessage(message)) {
                result.sessionId = message.session_id;
                result.toolsUsed = message.tools;

                logSuccess('Session initialized');
                logInfo(`Session ID: ${message.session_id}`);
                logInfo(`Available tools: ${message.tools.join(', ')}`);
                logInfo(`Model: ${message.model}`);
                logInfo(`Permission mode: ${message.permissionMode}`);

                if (message.mcp_servers && message.mcp_servers.length > 0) {
                    logInfo(`MCP servers: ${message.mcp_servers.map(s => `${s.name}(${s.status})`).join(', ')}`);
                }
                logSeparator();
                continue;
            }

            // Handle assistant messages (tool calls, thinking)
            if (isAssistantMessage(message)) {
                const assistantMsg = message as { message?: { content?: unknown[] } };
                const content = assistantMsg.message?.content;

                if (Array.isArray(content)) {
                    for (const block of content) {
                        const blockObj = block as { type?: string; name?: string; text?: string };
                        if (blockObj.type === 'tool_use') {
                            toolCallCount++;
                            logInfo(`Tool call #${toolCallCount}: ${blockObj.name || 'unknown'}`);
                        } else if (blockObj.type === 'text' && blockObj.text) {
                            // Show thinking/reasoning (truncated)
                            const preview = blockObj.text.length > 200
                                ? blockObj.text.substring(0, 200) + '...'
                                : blockObj.text;
                            logDebug(`Assistant: ${preview}`);
                        }
                    }
                }
                continue;
            }

            // Handle successful result
            if (isSuccessResultMessage(message)) {
                result.success = true;
                result.result = message.result;
                result.totalCostUsd = message.total_cost_usd;
                result.durationMs = message.duration_ms;
                result.numTurns = message.num_turns;

                logSeparator();
                logSuccess('Scan completed successfully!');
                logInfo(`Duration: ${(message.duration_ms / 1000).toFixed(2)}s`);
                logInfo(`Turns: ${message.num_turns}`);
                logInfo(`Cost: $${message.total_cost_usd.toFixed(4)} USD`);
                logInfo(`Input tokens: ${message.usage.input_tokens}`);
                logInfo(`Output tokens: ${message.usage.output_tokens}`);

                if (message.usage.cache_read_input_tokens) {
                    logInfo(`Cache read tokens: ${message.usage.cache_read_input_tokens}`);
                }

                if (message.permission_denials && message.permission_denials.length > 0) {
                    logWarn(`Permission denials: ${message.permission_denials.length}`);
                    for (const denial of message.permission_denials) {
                        logWarn(`  - Tool: ${denial.tool_name}`);
                    }
                }
                continue;
            }

            // Handle error result
            if (isErrorResultMessage(message)) {
                const errorMsg = message as SDKResultMessage & { errors?: string[] };
                result.success = false;
                result.errors = errorMsg.errors || [`Error subtype: ${message.subtype}`];
                result.totalCostUsd = message.total_cost_usd;
                result.durationMs = message.duration_ms;
                result.numTurns = message.num_turns;

                logSeparator();
                logError(`Scan failed with subtype: ${message.subtype}`);

                if (errorMsg.errors) {
                    for (const err of errorMsg.errors) {
                        logError(`  - ${err}`);
                    }
                }
                continue;
            }
        }

    } catch (error) {
        result.success = false;
        const errorMessage = error instanceof Error ? error.message : String(error);
        result.errors.push(errorMessage);

        logSeparator();
        logError(`Agent SDK error: ${errorMessage}`);

        if (error instanceof Error && error.stack) {
            logDebug(error.stack);
        }
    }

    result.durationMs = result.durationMs || (Date.now() - startTime);
    return result;
}

// ============================================================================
// Output Formatting
// ============================================================================

function printFinalResult(result: ScanResult): void {
    logSeparator();
    console.log('\n📋 FINAL SCAN RESULT:\n');
    console.log('─'.repeat(80));

    if (result.success && result.result) {
        console.log(result.result);
    } else if (result.errors.length > 0) {
        console.log('❌ Scan failed with errors:');
        for (const err of result.errors) {
            console.log(`   • ${err}`);
        }
    } else {
        console.log('❌ Scan completed with no result.');
    }

    console.log('\n' + '─'.repeat(80));
    console.log('\n📊 EXECUTION SUMMARY:');
    console.log(`   • Session ID: ${result.sessionId || 'N/A'}`);
    console.log(`   • Success: ${result.success ? '✅ Yes' : '❌ No'}`);
    console.log(`   • Duration: ${(result.durationMs / 1000).toFixed(2)}s`);
    console.log(`   • Turns: ${result.numTurns}`);
    console.log(`   • Cost: $${result.totalCostUsd.toFixed(4)} USD`);
    console.log(`   • Tools available: ${result.toolsUsed.join(', ') || 'N/A'}`);
    console.log('');
}

// ============================================================================
// Main Entry Point
// ============================================================================

async function main(): Promise<void> {
    console.log('\n🚀 Claude Agent SDK - Laravel Project Scanner\n');
    console.log('═'.repeat(80));

    // Parse arguments
    const args = process.argv.slice(2);
    const repoPath = args[0] || process.cwd();

    // Validate environment and paths
    validateEnvironment();
    const validatedPath = validateRepoPath(repoPath);

    // Build configuration
    const config: ScanConfig = {
        ...DEFAULT_CONFIG,
        repoPath: validatedPath,
    };

    // Run the scan
    const result = await runAgentScan(config);

    // Print final result
    printFinalResult(result);

    // Exit with appropriate code
    process.exit(result.success ? 0 : 1);
}

// Run main function
main().catch((error) => {
    logError(`Unhandled error: ${error}`);
    process.exit(1);
});
