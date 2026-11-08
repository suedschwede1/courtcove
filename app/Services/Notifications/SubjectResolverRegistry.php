<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Notifications\Types\NotificationType;

/**
 * Map from notification type to its {@see SubjectResolver} (bound as a singleton in AppServiceProvider).
 * A type without a resolver cannot be sent; the dispatcher skips its rows as `subject_gone`.
 */
final class SubjectResolverRegistry
{
    /** @var array<string, SubjectResolver|class-string<SubjectResolver>> */
    private array $resolvers = [];

    /** @param  array<string, SubjectResolver|class-string<SubjectResolver>>  $resolvers  keyed by type value */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $type => $resolver) {
            $this->resolvers[$type] = $resolver;
        }
    }

    /** @param  SubjectResolver|class-string<SubjectResolver>  $resolver */
    public function register(NotificationType $type, SubjectResolver|string $resolver): void
    {
        $this->resolvers[$type->value] = $resolver;
    }

    public function for(NotificationType $type): ?SubjectResolver
    {
        $resolver = $this->resolvers[$type->value] ?? null;
        if (is_string($resolver)) {
            $resolver = app($resolver);
            $this->resolvers[$type->value] = $resolver;
        }

        return $resolver instanceof SubjectResolver ? $resolver : null;
    }
}
