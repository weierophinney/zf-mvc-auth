<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\MvcAuth\Authentication;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zend\Http\Request;
use Zend\Http\Response;
use ZF\MvcAuth\Authentication\AdapterInterface;
use ZF\MvcAuth\Authentication\CompositeAdapter;
use ZF\MvcAuth\MvcAuthEvent;

class CompositeAdapterTest extends TestCase
{
    /**
     * @var CompositeAdapter
     */
    protected $adapter;

    public function setUp()
    {
        $this->adapter = new CompositeAdapter();
    }

    public function testCanAddAdapter()
    {
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;
        $this->adapter->addAdapter($adapterMock);

        $this->assertEquals($mockProvides, $this->adapter->provides());
        $this->assertTrue($this->adapter->matches('foo'));
        $this->assertTrue($this->adapter->matches('bar'));
    }

    public function testAddAdapterIsIdemPotent()
    {
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;
        $this->adapter->addAdapter($adapterMock);

        $oldAdapter = serialize($this->adapter);

        $this->adapter->addAdapter($adapterMock);

        $newAdapter = serialize($this->adapter);

        $this->assertSame($oldAdapter, $newAdapter);
    }

    public function testCanAddMultipleAdapters()
    {
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;

        $this->adapter->addAdapter($adapterMock1);
        $this->adapter->addAdapter($adapterMock2);

        $this->assertEquals(
            array_values(array_unique(array_merge($mock1Provides, $mock2Provides))),
            $this->adapter->provides()
        );
    }

    public function testCanAddAdaptersViaConstructor()
    {
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;

        $adapter = new CompositeAdapter([$adapterMock1, $adapterMock2]);

        $this->assertEquals(
            array_values(array_unique(array_merge($mock1Provides, $mock2Provides))),
            $adapter->provides()
        );
    }

    public function testCanRemoveAdapter()
    {
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;
        $this->adapter->addAdapter($adapterMock);
        $this->adapter->removeAdapter($adapterMock);

        $this->assertEmpty($this->adapter->provides());
    }

    public function testCanRemoveAdapterByType()
    {
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;

        $this->adapter->addAdapter($adapterMock1);
        $this->adapter->addAdapter($adapterMock2);

        $this->adapter->removeAdapter('baz');

        $this->assertEquals($mock1Provides, $this->adapter->provides());
    }

    /**
     * @param mixed $invalidValue
     *
     * @dataProvider invalidRemoveValues
     */
    public function testRemoveAdapterWithInvalidValuesThrows($invalidValue)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->removeAdapter($invalidValue);
    }

    public function invalidRemoveValues()
    {
        return [
            [0],
            [new stdClass()],
            [true],
            [[]],
        ];
    }

    public function testPreviouslyAddedAdaptersCanHandleTypesSupersededByRemovedOnes()
    {
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;

        $this->adapter->addAdapter($adapterMock1);
        $this->adapter->addAdapter($adapterMock2);

        $this->adapter->removeAdapter($adapterMock2);

        $this->assertEquals($mock1Provides, $this->adapter->provides());
    }

    public function testCanGetTypeFromRequest()
    {
        $request      = new Request();
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;
        $adapterMock
            ->expects($this->any())
            ->method('getTypeFromRequest')
            ->with($request)
            ->will($this->returnValue('foo'))
        ;

        $this->adapter->addAdapter($adapterMock);

        $this->assertEquals('foo', $this->adapter->getTypeFromRequest($request));
    }

    public function testGetTypeFromRequestCanReturnFalse()
    {
        $request      = new Request();
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;
        $adapterMock
            ->expects($this->any())
            ->method('getTypeFromRequest')
            ->with($request)
            ->will($this->returnValue(false))
        ;

        $this->adapter->addAdapter($adapterMock);

        $this->assertFalse($this->adapter->getTypeFromRequest($request));
    }

    public function testDelegatesAuthentication()
    {
        $request      = new Request();
        $response     = new Response();
        $event        = $this->getMockBuilder(MvcAuthEvent::class)->disableOriginalConstructor()->getMock();
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock1
            ->expects($this->any())
            ->method('getTypeFromRequest')
            ->with($request)
            ->will($this->returnValue('bar'))
        ;
        $adapterMock1
            ->expects($this->never()) // we assert that the first adapter is superseded by the second one
            ->method('authenticate')
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('getTypeFromRequest')
            ->with($request)
            ->will($this->returnValue('bar'))
        ;
        $adapterMock2
            ->expects($this->atLeastOnce())
            ->method('authenticate')
            ->with($request, $response, $event)
            ->will($this->returnValue(true))
        ;

        $this->adapter->addAdapter($adapterMock1);
        $this->adapter->addAdapter($adapterMock2);

        $this->assertTrue($this->adapter->authenticate($request, $response, $event));
    }

    public function testAuthenticationCanReturnFalse()
    {
        $request      = new Request();
        $response     = new Response();
        $event        = $this->getMockBuilder(MvcAuthEvent::class)->disableOriginalConstructor()->getMock();
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;
        $adapterMock
            ->expects($this->any())
            ->method('getTypeFromRequest')
            ->with($request)
            ->will($this->returnValue(false))
        ;

        $this->adapter->addAdapter($adapterMock);

        $this->assertFalse($this->adapter->authenticate($request, $response, $event));
    }

    public function testDelegatesPreAuth()
    {
        $request      = new Request();
        $response     = new Response();
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock1
            ->expects($this->atLeastOnce())
            ->method('preAuth')
            ->with($request, $response)
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;
        $adapterMock2
            ->expects($this->atLeastOnce())
            ->method('preAuth')
            ->with($request, $response)
        ;

        $this->adapter->addAdapter($adapterMock1);
        $this->adapter->addAdapter($adapterMock2);
        $this->adapter->preAuth($request, $response);
    }

    public function testPreAuthShortCircuitsWhenAnAdapterReturnsAResponse()
    {
        $request      = new Request();
        $response     = new Response();
        $adapterMock1 = $this->createMock(AdapterInterface::class);
        $adapterMock2 = $this->createMock(AdapterInterface::class);
        $mock1Provides = ['foo', 'bar'];
        $mock2Provides = ['bar', 'baz'];
        $adapterMock1
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock1Provides))
        ;
        $adapterMock1
            ->expects($this->atLeastOnce())
            ->method('preAuth')
            ->with($request, $response)
            ->will($this->returnValue($response))
        ;
        $adapterMock2
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mock2Provides))
        ;
        $adapterMock2
            ->expects($this->never())
            ->method('preAuth')
        ;

        $this->adapter->addAdapter($adapterMock1);
        $this->adapter->addAdapter($adapterMock2);
        $this->assertSame($response, $this->adapter->preAuth($request, $response));
    }

    public function testCanBeInitializedWithName()
    {
        $adapterMock = $this->createMock(AdapterInterface::class);
        $mockProvides = ['foo', 'bar'];
        $adapterMock
            ->expects($this->any())
            ->method('provides')
            ->will($this->returnValue($mockProvides))
        ;

        $adapter = new CompositeAdapter([$adapterMock], 'baz');

        $this->assertContains('bar', $adapter->provides());
        $this->assertContains('foo', $adapter->provides());
        $this->assertContains('baz', $adapter->provides());
        $this->assertTrue($adapter->matches('foo'));
        $this->assertTrue($adapter->matches('bar'));
        $this->assertTrue($adapter->matches('baz'));
    }
}
