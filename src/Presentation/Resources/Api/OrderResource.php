<?php

declare(strict_types=1);

namespace Presentation\Resources\Api;

use Billing\Domain\Entities\OrderEntity;
use JsonSerializable;
use Presentation\Resources\CurrencyResource;
use Presentation\Resources\DateTimeResource;

class OrderResource implements JsonSerializable
{
    use Traits\TwigResource;

    public function __construct(private OrderEntity $order)
    {
    }

    public function jsonSerialize(): array
    {
        $o = $this->order;

        return [
            'id' => $o->getId(),
            'currency' => new CurrencyResource($o->getCurrencyCode()),
            'status' => $o->getStatus(),
            'trial_period_days' => $o->getTrialPeriodDays(),
            'created_at' => new DateTimeResource($o->getCreatedAt()),
            'updated_at' => new DateTimeResource($o->getUpdatedAt()),
            'is_paid' => $o->isPaid(),
            'is_fulfilled' => $o->isFulfilled(),
            'plan' => new PlanSnapshotResource($o->getPlan()),
            'total' => $o->getTotalPrice(),
        ];
    }
}
