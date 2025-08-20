<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronJob extends Model
{
    protected $fillable = [
        'name',
        'command',
        'cron_expression',
        'description',
        'is_active',
        'last_run_at',
        'next_run_at',
        'run_count',
        'failure_count',
        'last_output',
        'last_error',
        'timeout_seconds',
        'max_attempts',
        'environment',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'run_count' => 'integer',
        'failure_count' => 'integer',
        'timeout_seconds' => 'integer',
        'max_attempts' => 'integer',
    ];

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->next_run_at && $this->next_run_at->isPast()) {
            return 'overdue';
        }

        if ($this->failure_count > 0) {
            return 'warning';
        }

        return 'active';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'inactive' => 'gray',
            'overdue' => 'danger',
            'warning' => 'warning',
            'active' => 'success',
            default => 'gray',
        };
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->run_count === 0) {
            return 100;
        }

        $successCount = $this->run_count - $this->failure_count;
        return ($successCount / $this->run_count) * 100;
    }

    public function getNextRunFormattedAttribute(): string
    {
        if (!$this->next_run_at) {
            return 'Not scheduled';
        }

        return $this->next_run_at->diffForHumans();
    }

    public function getLastRunFormattedAttribute(): string
    {
        if (!$this->last_run_at) {
            return 'Never run';
        }

        return $this->last_run_at->diffForHumans();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_active', true)
                    ->where('next_run_at', '<', now());
    }

    public function scopeByEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    public function calculateNextRun(): void
    {
        try {
            $cron = new \Cron\CronExpression($this->cron_expression);
            $this->next_run_at = $cron->getNextRunDate();
            $this->save();
        } catch (\Exception $e) {
            // Invalid cron expression
            $this->next_run_at = null;
            $this->save();
        }
    }

    public function execute(): array
    {
        $startTime = microtime(true);
        $output = '';
        $error = '';
        $success = false;

        try {
            // Build the full command
            $fullCommand = "cd " . base_path() . " && timeout {$this->timeout_seconds} {$this->command} 2>&1";
            
            // Execute command
            $output = shell_exec($fullCommand);
            
            if ($output === null) {
                throw new \Exception('Command execution failed or timed out');
            }

            $success = true;
            $this->increment('run_count');

        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->increment('failure_count');
        }

        $executionTime = microtime(true) - $startTime;

        // Update job record
        $this->update([
            'last_run_at' => now(),
            'last_output' => $output,
            'last_error' => $error,
        ]);

        // Calculate next run
        $this->calculateNextRun();

        return [
            'success' => $success,
            'output' => $output,
            'error' => $error,
            'execution_time' => $executionTime,
        ];
    }

    public function toggle(): void
    {
        $this->update(['is_active' => !$this->is_active]);
        
        if ($this->is_active) {
            $this->calculateNextRun();
        }
    }
}