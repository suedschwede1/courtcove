<?php

namespace Tests\Support;

trait NormalizesHtml
{
    protected function html(string $html): string
    {
        $html = trim($html);
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/>\s+</', '><', $html);

        return (string) preg_replace('/\s+\/>/', '/>', $html);
    }
}
