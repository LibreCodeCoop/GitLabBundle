<?php

/*
 * This file is part of the GitLabBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\GitLabBundle\EventSubscriber;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Event\AbstractTimesheetEvent;
use App\Event\TimesheetCreatePostEvent;
use App\Event\TimesheetDeleteMultiplePreEvent;
use App\Event\TimesheetDeletePreEvent;
use App\Event\TimesheetDuplicatePostEvent;
use App\Event\TimesheetStopPostEvent;
use App\Event\TimesheetUpdateMultiplePostEvent;
use App\Event\TimesheetUpdatePostEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TimesheetSubscriber implements EventSubscriberInterface
{
    /**
     * @var SystemConfiguration
     */
    private $configuration;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var HttpClientInterface
     */
    private $client;
    /**
     * @var string
     */
    private $gitLabToken;
    /**
     * @var string
     */
    private $gitLabBaseUrl;
    public function __construct(SystemConfiguration $configuration, EntityManagerInterface $entityManager, HttpClientInterface $client)
    {
        $this->configuration = $configuration;
        $this->entityManager = $entityManager;
        $this->client = $client;
        $this->gitLabToken = $this->configuration->find('gitlab_private_token');
        $this->gitLabBaseUrl = $this->configuration->find('gitlab_instance_base_url');
    }

    public static function getSubscribedEvents()
    {
        return [
            TimesheetCreatePostEvent::class => ['onAction', 100],
            TimesheetUpdatePostEvent::class => ['onAction', 100],
            TimesheetUpdateMultiplePostEvent::class => ['onAction', 100],
            TimesheetDuplicatePostEvent::class => ['onAction', 100],
            TimesheetStopPostEvent::class => ['onAction', 100],
            TimesheetDeletePreEvent::class => ['onAction', 100],
            TimesheetDeleteMultiplePreEvent::class => ['onAction', 100]
        ];
    }

    public function onAction(AbstractTimesheetEvent $event): void
    {
        if (!$this->gitLabToken) {
            return;
        }
        $timesheet = $event->getTimesheet();
        $issueId = $timesheet->getMetaField('gitlab_issue_id');
        if (!$issueId) {
            return;
        }
        $project = $timesheet->getProject();
        $projectId = $project->getMetaField('gitlab_project_id');
        if (!$projectId) {
            return;
        }
        $totalDuration = $this->sumDuration($projectId->getValue(), $issueId->getValue());
        $this->updateSpend($projectId->getValue(), $issueId->getValue(), $totalDuration);
    }

    private function sumDuration($projectId, $issueId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COALESCE(SUM(t.duration), 0) as duration')
            ->from(Timesheet::class, 't')
            ->join('t.meta', 'tm')
            ->join('t.project', 'p')
            ->join('p.meta', 'pm')
            ->where($qb->expr()->eq('tm.name', ':timesheetMetaName'))
            ->setParameter('timesheetMetaName', 'gitlab_issue_id')
            ->andWhere($qb->expr()->eq('tm.value', ':timesheetMetaValue'))
            ->setParameter('timesheetMetaValue', $issueId)
            ->andWhere($qb->expr()->eq('pm.name', ':projectMetaName'))
            ->setParameter('projectMetaName', 'gitlab_project_id')
            ->andWhere($qb->expr()->eq('pm.value', ':projectMetaValue'))
            ->setParameter('projectMetaValue', $projectId)
            ->groupBy('p.id');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function updateSpend($projectId, $issueId, $totalDuration)
    {
        $humanDuration = $this->secondsToHuman($totalDuration);
        try {
            $response = $this->client->request(
                'POST',
                $this->gitLabBaseUrl . '/api/v4/projects/' . $projectId . '/issues/' . $issueId . '/reset_spent_time', [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->gitLabToken,
                ],
            ]);
            $response = $this->client->request(
                'POST',
                $this->gitLabBaseUrl . '/api/v4/projects/' . $projectId . '/issues/' . $issueId . '/add_spent_time?duration=' . $humanDuration, [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->gitLabToken,
                ],
            ]);
        } catch (TransportExceptionInterface $e) {
        }
    }

    private function secondsToHuman(int $totalDuration): string
    {
        $human = '';

        $hours = intdiv(intdiv($totalDuration, 60), 60);
        if ($hours >= 1) {
            $human = $hours.'h';
        }

        $min = ($totalDuration / 60) % 60;
        if ($min) {
            $human.= $min . 'm';
        }
        return $human;
    }
}