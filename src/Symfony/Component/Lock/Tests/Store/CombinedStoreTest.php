<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Quorum\UnanimousQuorum;
use Symfony\Component\Lock\QuorumInterface;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CombinedStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 2000000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        $redis = new \Predis\Client('tcp://'.getenv('REDIS_HOST').':6379');
        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        return new CombinedStore(array(new RedisStore($redis)), new UnanimousQuorum());
    }

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $quorum;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $store1;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $store2;
    /** @var CombinedStore */
    private $store;

    public function setup()
    {
        $this->quorum = $this->getMockBuilder(QuorumInterface::class)->getMock();
        $this->store1 = $this->getMockBuilder(StoreInterface::class)->getMock();
        $this->store2 = $this->getMockBuilder(StoreInterface::class)->getMock();

        $this->store = new CombinedStore(array($this->store1, $this->store2), $this->quorum);
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function testSaveThrowsExceptionOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->quorum
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->save($key);
    }

    public function testSaveCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects($this->once())
            ->method('delete');
        $this->store2
            ->expects($this->once())
            ->method('delete');

        $this->quorum
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testSaveAbortWhenQuorumCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->never())
            ->method('save');

        $this->quorum
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function testputOffExpirationThrowsExceptionOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $ttl)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $ttl)
            ->willThrowException(new LockConflictedException());

        $this->quorum
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->putOffExpiration($key, $ttl);
    }

    public function testputOffExpirationCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $ttl)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $ttl)
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects($this->once())
            ->method('delete');
        $this->store2
            ->expects($this->once())
            ->method('delete');

        $this->quorum
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationAbortWhenQuorumCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $ttl)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->never())
            ->method('putOffExpiration');

        $this->quorum
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationIgnoreNonExpiringStorage()
    {
        $store1 = $this->getMockBuilder(StoreInterface::class)->getMock();
        $store2 = $this->getMockBuilder(StoreInterface::class)->getMock();

        $store = new CombinedStore(array($store1, $store2), $this->quorum);

        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->quorum
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->quorum
            ->expects($this->any())
            ->method('isMet')
            ->with(2, 2)
            ->willReturn(true);

        $store->putOffExpiration($key, $ttl);
    }

    public function testExistsDontAskToEveryBody()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects($this->never())
            ->method('exists');

        $this->quorum
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->quorum
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(true);

        $this->assertTrue($this->store->exists($key));
    }

    public function testExistsAbortWhenQuorumCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects($this->never())
            ->method('exists');

        $this->quorum
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->quorum
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(false);

        $this->assertFalse($this->store->exists($key));
    }

    public function testDeleteDontStopOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \Exception());
        $this->store2
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $this->store->delete($key);
    }
}
