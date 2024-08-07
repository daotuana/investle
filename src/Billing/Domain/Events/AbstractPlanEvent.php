<?php

declare(strict_types=1);

namespace Billing\Domain\Events;

use Billing\Domain\Entities\PlanEntity;

abstract class AbstractPlanEvent
{
    /**
     * @param PlanEntity $plan
     * @return void
     */
    public function __construct(
        public readonly PlanEntity $plan,
    ) {
    }
}
