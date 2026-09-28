<?php

namespace App\Http\Controllers\SuperUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\AuditReviewRequest;
use App\Services\Privacy\CleanupExpiredProofsService;
use App\Services\Privacy\GetAuditReviewService;
use Inertia\Inertia;
use Inertia\Response;

final class AuditReviewController extends Controller
{
    public function __construct(
        private readonly GetAuditReviewService $service,
        private readonly CleanupExpiredProofsService $proofs,
    ) {}

    public function __invoke(AuditReviewRequest $request): Response
    {
        return Inertia::render('super-user/audit/index', [
            'audit' => $this->service->handle($request->identity(), $request->filters()),
            'filters' => $request->filters(),
            'proofCleanup' => $this->proofs->summary(),
        ]);
    }
}
