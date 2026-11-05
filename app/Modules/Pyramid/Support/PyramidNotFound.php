<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

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
     * @param  bool  $hasLadder  false when there is no ladder at all: the way back then leads to the calendar
     */
    public function __construct(string $message, private readonly bool $admin = false, private readonly bool $hasLadder = true)
    {
        parent::__construct($message);
    }

    public static function abort(string $message, bool $admin = false, bool $hasLadder = true): never
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
            'hasLadder' => $this->hasLadder,
        ], 404);
    }
}
