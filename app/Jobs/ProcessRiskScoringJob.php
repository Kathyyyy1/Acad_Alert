<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\RiskScoringService;

class ProcessRiskScoringJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $blockId;
    protected $gradingPeriod;
    protected $schoolYear;

    public $tries = 3;
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(int $blockId, string $gradingPeriod, string $schoolYear)
    {
        $this->blockId = $blockId;
        $this->gradingPeriod = $gradingPeriod;
        $this->schoolYear = $schoolYear;
    }

    /**
     * Execute the job.
     */
    public function handle(RiskScoringService $riskService): void
    {
        $result = $riskService->processBlock(
            $this->blockId,
            $this->gradingPeriod,
            $this->schoolYear
        );

        // Store result for later retrieval
        \Illuminate\Support\Facades\Cache::put(
            'risk_scoring_' . $this->blockId . '_' . $this->gradingPeriod,
            $result,
            3600
        );
    }

    /**
     * Get the job result.
     */
    public function getResult(): ?array
    {
        return \Illuminate\Support\Facades\Cache::get(
            'risk_scoring_' . $this->blockId . '_' . $this->gradingPeriod
        );
    }
}