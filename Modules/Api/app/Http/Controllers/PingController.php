<?php

declare(strict_types=1);

namespace Modules\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PingController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->success(['status' => 'ok']);
    }
}
