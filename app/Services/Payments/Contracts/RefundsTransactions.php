<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Data\RefundResult;

interface RefundsTransactions
{
    public function refund(RefundRequest $request): RefundResult;
}
