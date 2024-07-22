<?php

declare(strict_types=1);

namespace Workspace\Application\CommandHandlers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Workspace\Application\Commands\CreateWorkspaceCommand;
use Workspace\Domain\Entities\WorkspaceEntity;
use Workspace\Domain\Events\WorkspaceCreatedEvent;
use Workspace\Domain\Repositories\WorkspaceRepositoryInterface;

class CreateWorkspaceCommandHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $repo,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function handle(CreateWorkspaceCommand $cmd): WorkspaceEntity
    {
        $user = $cmd->user;
        $workspace = $user->createWorkspace($cmd->name);

        if ($cmd->address) {
            $workspace->setAddress($cmd->address);
        }

        // Add the workspace to the repository
        $this->repo->add($workspace);

        // Dispatch the workspace created event
        $event = new WorkspaceCreatedEvent($workspace);
        $this->dispatcher->dispatch($event);

        return $workspace;
    }
}