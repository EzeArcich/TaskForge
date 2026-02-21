<?php

namespace Tests\Unit;

use App\Infrastructure\Calendar\CalendarProviderFactory;
use App\Infrastructure\Calendar\GoogleCalendarProvider;
use InvalidArgumentException;
use Tests\TestCase;

class CalendarProviderFactoryTest extends TestCase
{
    private CalendarProviderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CalendarProviderFactory();
    }

    public function test_supports_google(): void
    {
        $this->assertTrue($this->factory->supports('google'));
    }

    public function test_does_not_support_unknown_provider(): void
    {
        $this->assertFalse($this->factory->supports('outlook'));
    }

    public function test_make_google_returns_provider(): void
    {
        $provider = $this->factory->make('google');
        $this->assertInstanceOf(GoogleCalendarProvider::class, $provider);
    }

    public function test_make_unknown_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->make('outlook');
    }
}
