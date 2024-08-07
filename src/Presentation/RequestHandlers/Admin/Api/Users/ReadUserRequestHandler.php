<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Admin\Api\Users;

use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Presentation\Exceptions\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Shared\Infrastructure\CommandBus\Exception\NoHandlerFoundException;
use Presentation\Response\JsonResponse;
use User\Application\Commands\ReadUserCommand;
use Presentation\Resources\Admin\Api\UserResource;
use User\Domain\Exceptions\UserNotFoundException;

#[Route(path: '/[uuid:id]', method: RequestMethod::GET)]
class ReadUserRequestHandler extends UserApi implements
    RequestHandlerInterface
{
    /**
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function __construct(
        private Dispatcher $dispatcher
    ) {
    }

    /**
     * @param ServerRequestInterface $request 
     * @return ResponseInterface 
     * @throws NotFoundException 
     * @throws NoHandlerFoundException 
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        $query = new ReadUserCommand($id);
        try {
            $user = $this->dispatcher->dispatch($query);
        } catch (UserNotFoundException $th) {
            throw new NotFoundException(
                param: 'id',
                previous: $th
            );
        }

        return new JsonResponse(new UserResource($user));
    }
}
