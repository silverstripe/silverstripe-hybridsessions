<?php

namespace SilverStripe\HybridSessions\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\HybridSessions\HybridSession;
use SilverStripe\HybridSessions\Store\BaseStore;
use SilverStripe\HybridSessions\Tests\Store\TestCookieStore;

class HybridSessionTest extends SapphireTest
{
    /**
     * @var BaseStore
     */
    protected $handler;

    /**
     * @var HybridSession
     */
    protected $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->createMock(TestCookieStore::class);

        $this->instance = new HybridSession();
    }

    public function testSetHandlersAlsoSetsKeyToEachHandler()
    {
        $this->instance->setKey('foobar');
        $this->handler->expects($this->once())->method('setKey')->with('foobar');
        $this->instance->setHandlers([$this->handler]);
    }

    public function testOpenDelegatesToAllHandlers()
    {
        $this->handler->expects($this->once())->method('open')->with('foo', 'bar');
        $this->instance->setHandlers([$this->handler]);
        $this->assertTrue($this->instance->open('foo', 'bar'), 'Method returns true after delegation');
    }

    public function testCloseDelegatesToAllHandlers()
    {
        $this->handler->expects($this->once())->method('close');
        $this->instance->setHandlers([$this->handler]);
        $this->assertTrue($this->instance->close(), 'Method returns true after delegation');
    }

    public function testReadReturnsEmptyStringWithNoHandlers()
    {
        $this->handler->expects($this->once())->method('read')->with('foosession')->willReturn(false);
        $this->instance->setHandlers([$this->handler]);
        $this->assertFalse($this->instance->read('foosession'));
    }

    public function testReadReturnsHandlerDelegateResult()
    {
        $this->handler->expects($this->once())->method('read')->with('foo.session')->willReturn('success!');
        $this->instance->setHandlers([$this->handler]);
        $this->assertSame('success!', $this->instance->read('foo.session'));
    }

    public function testWriteDelegatesToHandlerAndReturnsTrue()
    {
        $this->handler->expects($this->once())->method('write')->with('foo', 'bar')->willReturn(true);
        $this->instance->setHandlers([$this->handler]);
        $this->assertTrue($this->instance->write('foo', 'bar'));
    }

    public function testWriteReturnsFalseWithNoHandlers()
    {
        $this->assertFalse($this->instance->write('no', 'handlers'));
    }

    public function testDestroyDelegatesToHandler()
    {
        $this->handler->expects($this->once())->method('destroy')->with('sessid1234');
        $this->instance->setHandlers([$this->handler]);
        $this->assertTrue($this->instance->destroy('sessid1234'), 'Method returns true after delegation');
    }

    public function testGcDelegatesToHandlers()
    {
        $this->handler->expects($this->once())->method('gc')->with(12345);
        $this->instance->setHandlers([$this->handler]);
        $this->instance->gc(12345);
    }
}
