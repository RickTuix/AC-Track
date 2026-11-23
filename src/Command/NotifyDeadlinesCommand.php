<?php

namespace App\Command;

use App\Entity\Notification;
use App\Repository\IncompleteRecordRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-deadlines',
    description: 'Scans for approaching and overdue Incomplete-Grade (INC) deadlines and issues internal notifications.'
)]
class NotifyDeadlinesCommand extends Command
{
    public function __construct(
        private IncompleteRecordRepository $incRepository,
        private NotificationService $notificationService,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Hours threshold for approaching deadline notifications', 48);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');

        $io->title('AC.TRACK — Incomplete Grade Deadline Notifier');
        $io->text(sprintf('Scanning pending INC requirements due within %d hours or overdue...', $hours));

        $approachingRecords = $this->incRepository->findApproachingDeadlines($hours);
        $overdueRecords = $this->incRepository->findOverduePending();

        $notifiedCount = 0;
        $notifRepo = $this->em->getRepository(Notification::class);

        // 1. Process approaching records
        foreach ($approachingRecords as $record) {
            $studentUser = $record->getStudent()?->getUser();
            if (!$studentUser) {
                continue;
            }

            // Check if already notified for approaching deadline today
            $existing = $notifRepo->createQueryBuilder('n')
                ->where('n.user = :user')
                ->andWhere('n.type = :type')
                ->andWhere('n.link LIKE :link')
                ->setParameter('user', $studentUser)
                ->setParameter('type', 'DEADLINE_APPROACHING')
                ->setParameter('link', '%' . $record->getId())
                ->getQuery()
                ->getResult();

            if (count($existing) === 0) {
                $this->notificationService->notify(
                    $studentUser,
                    sprintf('Reminder: INC Deadline Approaching (%s)', $record->getSubject()?->getCode()),
                    sprintf(
                        'Your INC requirement "%s" for %s is due on %s. Please submit before the deadline.',
                        $record->getMissingItemTitle(),
                        $record->getSubject()?->getCode(),
                        $record->getDeadline()?->format('M d, Y h:i A')
                    ),
                    sprintf('/student/incompletes/%d', $record->getId()),
                    'DEADLINE_APPROACHING'
                );
                $notifiedCount++;
            }
        }

        // 2. Process overdue records
        foreach ($overdueRecords as $record) {
            $studentUser = $record->getStudent()?->getUser();
            if (!$studentUser) {
                continue;
            }

            $existing = $notifRepo->createQueryBuilder('n')
                ->where('n.user = :user')
                ->andWhere('n.type = :type')
                ->andWhere('n.link LIKE :link')
                ->setParameter('user', $studentUser)
                ->setParameter('type', 'DEADLINE_OVERDUE')
                ->setParameter('link', '%' . $record->getId())
                ->getQuery()
                ->getResult();

            if (count($existing) === 0) {
                $this->notificationService->notify(
                    $studentUser,
                    sprintf('Overdue Requirement: %s (%s)', $record->getMissingItemTitle(), $record->getSubject()?->getCode()),
                    sprintf(
                        'The deadline (%s) for "%s" has passed. Contact instructor %s immediately.',
                        $record->getDeadline()?->format('M d, Y h:i A'),
                        $record->getMissingItemTitle(),
                        $record->getTeacher()?->getFullName() ?? 'your teacher'
                    ),
                    sprintf('/student/incompletes/%d', $record->getId()),
                    'DEADLINE_OVERDUE'
                );
                $notifiedCount++;
            }
        }

        $io->success(sprintf('Scanned %d approaching and %d overdue records. Dispatched %d internal notifications.', count($approachingRecords), count($overdueRecords), $notifiedCount));

        return Command::SUCCESS;
    }
}
