<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\IndexTransferRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends BaseApiController
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function index(IndexTransferRequest $request): JsonResponse
    {
        $transfers = $this->transferService->list($request->validated());

        return $this->successResponse(
            TransferResource::collection($transfers)->response()->getData(true),
            'Transfers retrieved successfully'
        );
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = $this->transferService->store($request->validated());

        return $this->createdResponse(
            new TransferResource($transfer->load(['employee:id,staff_id,first_name_en,last_name_en', 'creator:id,name'])),
            'Transfer recorded successfully'
        );
    }

    public function show(Transfer $transfer): JsonResponse
    {
        $transfer = $this->transferService->show($transfer);

        return $this->successResponse(
            new TransferResource($transfer),
            'Transfer retrieved successfully'
        );
    }

    public function destroy(Transfer $transfer): JsonResponse
    {
        $this->transferService->destroy($transfer);

        return $this->successResponse(null, 'Transfer deleted successfully');
    }
}
