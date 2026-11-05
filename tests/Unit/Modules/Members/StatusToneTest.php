<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Support\StatusTone;
use App\Support\Ui\BadgeTone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatusToneTest extends TestCase
{
    #[Test]
    public function member_statuses_map_to_badge_tones(): void
    {
        $this->assertSame('good', StatusTone::member('aktiv'));
        $this->assertSame('attention', StatusTone::member('ruhend'));
        $this->assertSame('neutral', StatusTone::member('ausgetreten'));
        $this->assertSame('neutral', StatusTone::member(null));
    }

    #[Test]
    public function due_statuses_map_to_badge_tones(): void
    {
        $this->assertSame('good', StatusTone::due('bezahlt'));
        $this->assertSame('attention', StatusTone::due('ausstehend'));
        $this->assertSame('problem', StatusTone::due('ueberfaellig'));
        $this->assertSame('neutral', StatusTone::due('x'));
        $this->assertSame('neutral', StatusTone::due(null));
    }

    #[Test]
    public function badge_classes_map_each_status_to_its_modifier_class(): void
    {
        $this->assertSame([
            'bezahlt' => 'ds-badge--good',
            'ausstehend' => 'ds-badge--attention',
            'ueberfaellig' => 'ds-badge--problem',
        ], StatusTone::badgeClasses(StatusTone::DUE));
    }

    #[Test]
    public function every_tone_is_a_valid_badge_tone(): void
    {
        $valid = array_column(BadgeTone::cases(), 'value');

        foreach ([...array_values(StatusTone::MEMBER), ...array_values(StatusTone::DUE)] as $tone) {
            $this->assertContains($tone, $valid);
        }
    }
}
