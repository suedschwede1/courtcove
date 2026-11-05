<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use App\Modules\Pyramid\Services\SeedService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Seeding assertions of modulex/tests/pyramid.php of the old application, with fictional labels.
 */
class SeedServiceTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function field(): array
    {
        return (new SeedService)->seed([
            ['label' => 'Zeta', 'itn' => 9.5],
            ['label' => 'Alpha', 'itn' => null],
            ['label' => 'Mu', 'itn' => 7.1],
            ['label' => 'Beta', 'itn' => 5.7],
            ['label' => 'Omega', 'itn' => null],
        ], 10.0);
    }

    #[Test]
    public function seeding_orders_by_rated_itn_then_label(): void
    {
        $field = $this->field();

        $this->assertSame(['Beta', 'Mu', 'Zeta', 'Alpha', 'Omega'], array_column($field, 'label'));
        $this->assertSame([1, 2, 3, 4, 5], array_column($field, 'position'));
    }

    #[Test]
    public function every_participant_is_kept(): void
    {
        $this->assertCount(5, $this->field());
    }

    #[Test]
    public function missing_itn_is_rated_with_the_default(): void
    {
        $this->assertSame(10.0, $this->field()[3]['itn_rated']);
    }

    #[Test]
    public function the_real_itn_stays_empty(): void
    {
        $this->assertNull($this->field()[3]['itn']);
    }

    #[Test]
    public function a_real_itn_is_rated_as_is(): void
    {
        $this->assertSame(5.7, $this->field()[0]['itn_rated']);
    }

    #[Test]
    public function an_empty_field_gives_an_empty_ranking(): void
    {
        $this->assertSame([], (new SeedService)->seed([], 10.0));
    }

    #[Test]
    public function ties_compare_labels_by_byte_order(): void
    {
        $field = (new SeedService)->seed([
            ['label' => 'b', 'itn' => 5.7],
            ['label' => 'B', 'itn' => '5.7'],
            ['label' => 'a', 'itn' => 5.7],
        ], 10.0);

        $this->assertSame(['B', 'a', 'b'], array_column($field, 'label'));
    }

    #[Test]
    public function the_default_may_be_a_string(): void
    {
        $field = (new SeedService)->seed([['label' => 'Alpha', 'itn' => null]], '10.0');

        $this->assertSame(10.0, $field[0]['itn_rated']);
    }
}
