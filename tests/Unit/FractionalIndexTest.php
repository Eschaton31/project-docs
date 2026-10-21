<?php

namespace Tests\Unit;

use App\Support\FractionalIndex;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FractionalIndexTest extends TestCase
{
    public function test_key_sorts_between_its_bounds(): void
    {
        $first = FractionalIndex::between(null, null);
        $after = FractionalIndex::between($first, null);
        $before = FractionalIndex::between(null, $first);
        $middle = FractionalIndex::between($first, $after);

        $this->assertSame([$before, $first, $middle, $after], $this->sorted([$after, $middle, $first, $before]));
    }

    public function test_rejects_bounds_out_of_order(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FractionalIndex::between('b', 'a');
    }

    public function test_n_keys_are_ascending_unique_and_short(): void
    {
        $keys = FractionalIndex::nBetween(null, null, 1000);

        $this->assertCount(1000, array_unique($keys));
        $this->assertSame($keys, $this->sorted($keys));
        $this->assertLessThanOrEqual(3, max(array_map('strlen', $keys)));
    }

    public function test_random_inserts_keep_order(): void
    {
        mt_srand(42);
        $keys = [FractionalIndex::between(null, null)];

        for ($i = 0; $i < 2000; $i++) {
            $at = mt_rand(0, count($keys));
            $key = FractionalIndex::between($keys[$at - 1] ?? null, $keys[$at] ?? null);
            array_splice($keys, $at, 0, [$key]);
        }

        $this->assertCount(2001, array_unique($keys));
        $this->assertSame($keys, $this->sorted($keys));
        foreach ($keys as $key) {
            $this->assertStringEndsNotWith('0', $key);
        }
    }

    public function test_repeated_appends_and_prepends_stay_ordered(): void
    {
        $keys = [FractionalIndex::between(null, null)];
        for ($i = 0; $i < 300; $i++) {
            $keys[] = FractionalIndex::between(end($keys), null);
            array_unshift($keys, FractionalIndex::between(null, $keys[0]));
        }

        $this->assertSame($keys, $this->sorted($keys));
    }

    /**
     * Byte-wise sort, matching the utf8mb4_bin column collation.
     */
    private function sorted(array $keys): array
    {
        usort($keys, 'strcmp');

        return $keys;
    }
}
