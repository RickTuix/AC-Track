<?php

namespace App\Tests\Controller;

use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ReportAndNotificationTest extends WebTestCase
{
    public function testNotifyDeadlinesCommand(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:notify-deadlines');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--hours' => 48]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Incomplete Grade Deadline Notifier', $output);
    }

    public function testNotificationCenterAndMarkRead(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $student = $userRepo->findOneBy(['username' => 'student01']);

        $client->loginUser($student);
        $crawler = $client->request('GET', '/notifications');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Academic Notifications');
    }

    public function testStudentGradeReportHtmlAndPdf(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $student = $userRepo->findOneBy(['username' => 'student01']);

        $client->loginUser($student);

        // Test HTML printable view
        $client->request('GET', '/student/reports/grades');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'AC.TRACK ACADEMIC SYSTEM');

        // Test PDF generation endpoint
        $client->request('GET', '/student/reports/grades?pdf=1');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testAdminAcademicProgressReportCsvAndPdf(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['username' => 'admin']);

        $client->loginUser($admin);

        // Test HTML view
        $client->request('GET', '/admin/reports/academic-progress');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Academic Progress');

        // Test CSV Export
        $client->request('GET', '/admin/reports/academic-progress?format=csv');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');

        // Test PDF Export
        $client->request('GET', '/admin/reports/academic-progress?format=pdf');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
    }
}
