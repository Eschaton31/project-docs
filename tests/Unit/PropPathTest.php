<?php

namespace Tests\Unit;

use App\Ai\PropPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PropPathTest extends TestCase
{
    private array $props = [
        'title' => 'Overview',
        'list' => ['First', ['First.1', 'First.2'], 'Second', ['bold' => true, 'list' => ['Bold.1']]],
        'data' => [['item' => 'a', 'description' => ['line 1', 'line 2']]],
    ];

    public function test_it_reads_keys_list_indexes_and_nested_objects(): void
    {
        $this->assertSame('Overview', PropPath::get($this->props, 'title'));
        $this->assertSame('First.2', PropPath::get($this->props, 'list.1.1'));
        $this->assertSame('Bold.1', PropPath::get($this->props, 'list.3.list.0'));
        $this->assertSame('line 2', PropPath::get($this->props, 'data.0.description.1'));
        $this->assertNull(PropPath::get($this->props, 'list.9'));
        $this->assertNull(PropPath::get($this->props, 'title.0'));
    }

    public function test_it_sets_a_string_without_touching_the_rest(): void
    {
        $changed = PropPath::set($this->props, 'list.1.0', 'Changed');

        $this->assertSame('Changed', $changed['list'][1][0]);
        $this->assertSame('First.2', $changed['list'][1][1]);
        $this->assertSame(array_diff_key($this->props, ['list' => 1]), array_diff_key($changed, ['list' => 1]));
        $this->assertSame('First.1', $this->props['list'][1][0]);
    }

    public function test_it_refuses_to_set_a_path_that_is_not_a_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PropPath::set($this->props, 'list.1', 'not a sub-list');
    }

    public function test_it_refuses_to_create_new_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PropPath::set($this->props, 'list.9', 'new');
    }
}
