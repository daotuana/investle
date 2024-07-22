<?php

declare(strict_types=1);

namespace Workspace\Application\CommandHandlers;

use Workspace\Application\Commands\CountWorkspacesCommand;
use Workspace\Domain\Repositories\WorkspaceRepositoryInterface;

class CountWorkspacesCommandHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $repo,
    ) {
    }

    public function handle(CountWorkspacesCommand $cmd): int
    {
        $workspaces = $this->repo;

        if ($cmd->hasSubscription === true) {
            $workspaces = $workspaces->hasSubscription();
        }

        if ($cmd->query) {
            $workspaces = $workspaces->search($cmd->query);
        }

        return $workspaces->count();
    }
}
