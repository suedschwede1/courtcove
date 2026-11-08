<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

final readonly class TemplateRef
{
    /**
     * @param  string  $key  notification type key, e.g. `player_search_opened`
     * @param  string  $freeText  only used in sandbox mode, built from the language text
     */
    public function __construct(
        public ?string $contentId,
        public string $key,
        public string $locale,
        public string $freeText = '',
    ) {}
}
