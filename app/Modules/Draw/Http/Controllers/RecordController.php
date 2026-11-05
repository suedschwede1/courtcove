<?php

declare(strict_types=1);

namespace App\Modules\Draw\Http\Controllers;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Support\DrawRecordPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class RecordController extends Controller
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function show(string $slug): Response
    {
        $draw = Draw::query()->where('slug', $slug)->first();

        if ($draw === null) {
            return $this->noCache(response()->view('draw::record', [
                'draw' => null, 'payload' => [], 'result' => null, 'revealed' => false,
            ], 404));
        }

        $revealed = $draw->isRevealed();
        $result = $revealed && $draw->result !== null ? json_decode($draw->result, true) : null;

        return $this->noCache(response()->view('draw::record', [
            'draw' => $draw,
            'payload' => $draw->payloadArray(),
            'result' => is_array($result) ? $result : null,
            'revealed' => $revealed,
        ]));
    }

    public function json(string $slug): JsonResponse
    {
        $draw = Draw::query()->where('slug', $slug)->first();

        if ($draw === null) {
            return $this->noCache(response()->json(['error' => 'not found'], 404, [], self::JSON_FLAGS));
        }

        return $this->noCache(response()->json(DrawRecordPresenter::json($draw), 200, [], self::JSON_FLAGS));
    }

    /**
     * @template T of BaseResponse
     *
     * @param  T  $response
     * @return T
     */
    private function noCache(BaseResponse $response): BaseResponse
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
