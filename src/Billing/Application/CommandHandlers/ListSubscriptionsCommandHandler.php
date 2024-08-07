<?php

declare(strict_types=1);

namespace Billing\Application\CommandHandlers;

use Billing\Application\Commands\ListSubscriptionsCommand;
use Billing\Domain\Entities\SubscriptionEntity;
use Billing\Domain\Exceptions\SubscriptionNotFoundException;
use Billing\Domain\Repositories\SubscriptionRepositoryInterface;
use Shared\Domain\ValueObjects\CursorDirection;
use Traversable;

class ListSubscriptionsCommandHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $repo,
    ) {
    }

    /**
     * @return Traversable<SubscriptionEntity>
     * @throws SubscriptionNotFoundException
     */
    public function handle(ListSubscriptionsCommand $cmd): Traversable
    {
        $cursor = $cmd->cursor
            ? $this->repo->ofId($cmd->cursor)
            : null;

        $subs = $this->repo;

        if ($cmd->sortDirection) {
            $subs = $subs->sort($cmd->sortDirection, $cmd->sortParameter);
        }

        if ($cmd->status) {
            $subs = $subs->filterByStatus($cmd->status);
        }

        if ($cmd->workspace) {
            $subs = $subs->filterByWorkspace($cmd->workspace);
        }

        if ($cmd->plan) {
            $subs = $subs->filterByPlan($cmd->plan);
        }

        if ($cmd->maxResults) {
            $subs = $subs->setMaxResults($cmd->maxResults);
        }

        if ($cursor) {
            if ($cmd->cursorDirection == CursorDirection::ENDING_BEFORE) {
                return $subs = $subs->endingBefore($cursor);
            }

            return $subs->startingAfter($cursor);
        }

        return $subs;
    }
}
