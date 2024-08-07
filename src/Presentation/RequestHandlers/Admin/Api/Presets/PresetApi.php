<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Admin\Api\Presets;

use Easy\Router\Attributes\Path;
use Presentation\RequestHandlers\Admin\Api\AdminApi;

#[Path('/presets')]
abstract class PresetApi extends AdminApi
{
}
