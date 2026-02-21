<?php

namespace Tests\Unit;

use App\Infrastructure\Kanban\KanbanProviderFactory;
use App\Infrastructure\Kanban\TrelloProvider;
use InvalidArgumentException;
use Tests\TestCase;

class KanbanProviderFactoryTest extends TestCase
{
    private KanbanProviderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new KanbanProviderFactory();
    }

    public function test_supports_trello(): void
    {
        $this->assertTrue($this->factory->supports('trello'));
    }

    public function test_does_not_support_unknown_provider(): void
    {
        $this->assertFalse($this->factory->supports('jira'));
    }

    public function test_make_trello_returns_provider(): void
    {
        $provider = $this->factory->make('trello');
        $this->assertInstanceOf(TrelloProvider::class, $provider);
    }

    public function test_make_unknown_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->make('notion');
    }
}
