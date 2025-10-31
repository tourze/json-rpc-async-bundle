<?php

namespace Tourze\JsonRPCAsyncBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;

final class AsyncResultFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $result1 = new AsyncResult();
        $result1->setTaskId('test-task-1');
        $result1->setResult(['status' => 'success', 'data' => 'test result 1']);
        $manager->persist($result1);

        $result2 = new AsyncResult();
        $result2->setTaskId('test-task-2');
        $result2->setResult(['status' => 'pending']);
        $manager->persist($result2);

        $result3 = new AsyncResult();
        $result3->setTaskId('test-task-3');
        $result3->setResult(null);
        $manager->persist($result3);

        $manager->flush();
    }
}
