<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

use App\Modules\Pyramid\Services\PyramidRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A 404 of the Pyramid pages: still a NotFoundHttpException with the old text as message, but rendered as a
 * friendly page of the module (public layout, or the admin shell for the administration) with a way back.
 * JSON requests keep the framework's JSON answer.
 */
final class PyramidNotFound extends NotFoundHttpException
{
    /**
     * @param  bool  $admin  render inside layouts.admin and link back to the ladder overview of the administration
     * @param  bool|null  $hasLadder  false when there is no ladder at all: the way back then leads to the calendar;
     *                                null = look it up when rendering (no default ladder means /pyramide would 404 too)
     */
    public function __construct(string $message, private readonly bool $admin = false, private readonly ?bool $hasLadder = null)
    {
        parent::__construct($message);
    }

    public static function abort(string $message, bool $admin = false, ?bool $hasLadder = null): never
    {
        throw new self($message, $admin, $hasLadder);
    }

    public function render(Request $request): ?Response
    {
        if ($request->expectsJson()) {
            return null;
        }

        return response()->view($this->admin ? 'pyramid::admin.not-found' : 'pyramid::not-found', [
            'message' => $this->getMessage(),
            'hasLadder' => $this->hasLadder ?? app(PyramidRepository::class)->defaultLadder() !== null,
        ], 404);
    }
}
