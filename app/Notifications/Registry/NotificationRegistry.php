<?php

declare(strict_types=1);

namespace App\Notifications\Registry;

use App\Services\Notifications\SubjectResolver;
use InvalidArgumentException;

/**
 * The one place that knows which notification types and consent purposes exist (singleton, filled in
 * AppServiceProvider via {@see BuiltInNotifications}). Channel, dispatcher, consent, settings and admin
 * resolve a type by its string key here; a key that is not registered (any more) cannot be sent.
 *
 * Adding a type: `register(new NotificationTypeDefinition(...), new NotificationPurposeDefinition(...))`
 * (the purpose only when it is new), plus the resolver class and the texts.
 */
final class NotificationRegistry
{
    /** @var array<string, NotificationTypeDefinition> */
    private array $types = [];

    /** @var array<string, NotificationPurposeDefinition> */
    private array $purposes = [];

    /** @var array<string, SubjectResolver> resolved or swapped resolver instances by type key */
    private array $resolvers = [];

    /**
     * Registers (or replaces) a type, together with its purpose when given. The type's purpose must be
     * registered already or be the one passed; a follow-up must name a registered type.
     *
     * @throws InvalidArgumentException
     */
    public function register(NotificationTypeDefinition $type, ?NotificationPurposeDefinition $purpose = null): void
    {
        if ($purpose !== null) {
            if ($purpose->key !== $type->purpose) {
                throw new InvalidArgumentException("Purpose [{$purpose->key}] does not belong to notification type [{$type->key}].");
            }
            $this->registerPurpose($purpose);
        }
        if ($type->purpose !== null && ! isset($this->purposes[$type->purpose])) {
            throw new InvalidArgumentException("Notification type [{$type->key}] needs the unregistered purpose [{$type->purpose}].");
        }
        if ($type->followupOf !== null && ! isset($this->types[$type->followupOf])) {
            throw new InvalidArgumentException("Notification type [{$type->key}] follows up the unregistered type [{$type->followupOf}].");
        }

        $this->types[$type->key] = $type;
        unset($this->resolvers[$type->key]);
    }

    /** Registers (or replaces) a purpose without a type, e.g. before several types are added. */
    public function registerPurpose(NotificationPurposeDefinition $purpose): void
    {
        $this->purposes[$purpose->key] = $purpose;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function type(string $key): ?NotificationTypeDefinition
    {
        return $this->types[$key] ?? null;
    }

    /** @return array<string, NotificationTypeDefinition> in registration order */
    public function types(): array
    {
        return $this->types;
    }

    public function purpose(string $key): ?NotificationPurposeDefinition
    {
        return $this->purposes[$key] ?? null;
    }

    /** @return array<string, NotificationPurposeDefinition> */
    public function purposes(): array
    {
        return $this->purposes;
    }

    /**
     * Keys of all types of a purpose: their subscriptions go when its consent is revoked.
     *
     * @return list<string>
     */
    public function typesOfPurpose(string $purpose): array
    {
        return array_keys(array_filter($this->types, static fn (NotificationTypeDefinition $t): bool => $t->purpose === $purpose));
    }

    /**
     * Keys of the types switched on together with the consent of a purpose.
     *
     * @return list<string>
     */
    public function optInTypesOfPurpose(string $purpose): array
    {
        return array_keys(array_filter($this->types, static fn (NotificationTypeDefinition $t): bool => $t->purpose === $purpose && $t->optIn));
    }

    /**
     * Purposes whose subscribable types (not the admin test, not a follow-up) include no opt-in type: switching
     * such a purpose on in the account card subscribes nothing. Purposes without subscribable types (e.g. only
     * the admin test) are not listed. A registration check for tests and the runbook; it cannot run at
     * register() time because a purpose is registered before its types.
     *
     * @return list<string>
     */
    public function purposesWithoutOptInType(): array
    {
        $missing = [];
        foreach (array_keys($this->purposes) as $purpose) {
            $subscribable = array_filter($this->types, static fn (NotificationTypeDefinition $t): bool => $t->purpose === $purpose && $t->isSubscribable());
            if ($subscribable !== [] && array_filter($subscribable, static fn (NotificationTypeDefinition $t): bool => $t->optIn) === []) {
                $missing[] = $purpose;
            }
        }

        return $missing;
    }

    /**
     * Keys of the test types (admin test): they skip the daily limit and do not count towards it.
     *
     * @return list<string>
     */
    public function testTypeKeys(): array
    {
        return array_keys(array_filter($this->types, static fn (NotificationTypeDefinition $t): bool => $t->testType));
    }

    /** Resolver of a type (built from the container once); null for an unknown type. */
    public function resolver(string $key): ?SubjectResolver
    {
        if (isset($this->resolvers[$key])) {
            return $this->resolvers[$key];
        }

        $type = $this->types[$key] ?? null;
        if ($type === null) {
            return null;
        }

        $resolver = app($type->resolver);
        if (! $resolver instanceof SubjectResolver) {
            return null;
        }

        return $this->resolvers[$key] = $resolver;
    }

    /** Swaps the resolver instance of a registered type (tests, decorators). */
    public function useResolver(string $key, SubjectResolver $resolver): void
    {
        if (! isset($this->types[$key])) {
            throw new InvalidArgumentException("Unknown notification type [$key].");
        }

        $this->resolvers[$key] = $resolver;
    }
}
