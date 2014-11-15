<?php
/*
 * Copyright (c) 2012-2014 Janos Szurovecz
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace predaddy;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use PHPUnit_Framework_TestCase;
use predaddy\commandhandling\CommandBus;
use predaddy\domain\AggregateId;
use predaddy\domain\DecrementedEvent;
use predaddy\domain\DomainEvent;
use predaddy\domain\eventsourcing\CreateEventSourcedUser;
use predaddy\domain\eventsourcing\Decrement;
use predaddy\domain\eventsourcing\EventSourcingRepository;
use predaddy\domain\eventsourcing\Increment;
use predaddy\domain\impl\doctrine\DoctrineOrmEventStore;
use predaddy\domain\IncrementedEvent;
use predaddy\domain\UserCreated;
use predaddy\eventhandling\EventBus;
use predaddy\messagehandling\interceptors\EventPersister;
use predaddy\util\TransactionalBuses;
use predaddy\util\TransactionalBusesBuilder;
use trf4php\doctrine\DoctrineTransactionManager;

/**
 * Class DirectCommandBusIntegrationTest
 *
 * @package predaddy\commandhandling
 *
 * @author Janos Szurovecz <szjani@szjani.hu>
 * @group integration
 */
class DirectCommandBusIntegrationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var EventBus
     */
    private $eventBus;

    /**
     * @var CommandBus
     */
    private $commandBus;

    private static $entityManager;

    public static function setUpBeforeClass()
    {
        $isDevMode = true;
        $config = Setup::createAnnotationMetadataConfiguration(
            [str_replace(DIRECTORY_SEPARATOR . 'tests', '', __DIR__ . '/domain/impl/doctrine')],
            $isDevMode,
            '/tmp',
            null,
            false
        );

        $connectionOptions = ['driver' => 'pdo_sqlite', 'memory' => true];

        // obtaining the entity manager
        self::$entityManager =  EntityManager::create($connectionOptions, $config);

        $schemaTool = new SchemaTool(self::$entityManager);

        $cmf = self::$entityManager->getMetadataFactory();
        $classes = $cmf->getAllMetadata();

        $schemaTool->dropDatabase();
        $schemaTool->createSchema($classes);
    }

    protected function setUp()
    {
        $eventStore = new DoctrineOrmEventStore(self::$entityManager);
        $transactionalBuses = TransactionalBusesBuilder::create(new DoctrineTransactionManager(self::$entityManager))
            ->withRepository(new EventSourcingRepository($eventStore))
            ->useDirectCommandBus()
            ->interceptEventsWithinTransaction([new EventPersister($eventStore)])
            ->build();
        $this->commandBus = $transactionalBuses->commandBus();
        $this->eventBus = $transactionalBuses->eventBus();
    }

    public function testIntegration()
    {
        $currentVersion = null;
        $this->eventBus->registerClosure(
            function (DomainEvent $event) use (&$currentVersion) {
                $currentVersion = $event->stateHash();
            }
        );

        /* @var $aggregateId AggregateId */
        $aggregateId = null;
        $this->eventBus->registerClosure(
            function (UserCreated $event) use (&$aggregateId) {
                $aggregateId = $event->aggregateId();
            }
        );
        $this->commandBus->post(new CreateEventSourcedUser());
        self::assertNotNull($aggregateId);

        $incremented = 0;
        $this->eventBus->registerClosure(
            function (IncrementedEvent $event) use (&$incremented, $aggregateId) {
                DirectCommandBusIntegrationTest::assertEquals($aggregateId, $event->aggregateId());
                $incremented++;
            }
        );
        $this->commandBus->post(new Increment($aggregateId->value(), $currentVersion));
        $this->commandBus->post(new Increment($aggregateId->value(), $currentVersion));
        self::assertEquals(2, $incremented);

        $decremented = 0;
        $this->eventBus->registerClosure(
            function (DecrementedEvent $event) use (&$decremented, $aggregateId) {
                DirectCommandBusIntegrationTest::assertEquals($aggregateId, $event->aggregateId());
                $decremented++;
            }
        );
        $this->commandBus->post(new Decrement($aggregateId->value()));
        $this->commandBus->post(new Decrement($aggregateId->value()));
        self::assertEquals(2, $decremented);
    }
}
