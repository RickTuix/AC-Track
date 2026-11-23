<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginAndRoleRedirectTest extends WebTestCase
{
    public function testLoginPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'AC.TRACK');
        $this->assertSelectorExists('form[action="/login"]');
    }

    public function testFormSubmitLogin(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign In')->form([
            '_username' => 'student01',
            '_password' => 'password123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/student/dashboard');
    }

    public function testAdminLoginRedirectsToAdminDashboard(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['username' => 'admin']);

        $client->loginUser($admin);
        $client->request('GET', '/');

        $this->assertResponseRedirects('/admin/dashboard');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Registrar & Administrator Hub');
    }

    public function testTeacherLoginRedirectsToTeacherDashboard(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $teacher = $userRepo->findOneBy(['username' => 'teacher.santos']);

        $client->loginUser($teacher);
        $client->request('GET', '/');

        $this->assertResponseRedirects('/teacher/dashboard');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Santos');
    }

    public function testStudentLoginRedirectsToStudentDashboard(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $student = $userRepo->findOneBy(['username' => 'student01']);

        $client->loginUser($student);
        $client->request('GET', '/');

        $this->assertResponseRedirects('/student/dashboard');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome, Carl!');
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/dashboard');
        $this->assertResponseRedirects('http://localhost/login');

        $client->request('GET', '/teacher/dashboard');
        $this->assertResponseRedirects('http://localhost/login');

        $client->request('GET', '/student/dashboard');
        $this->assertResponseRedirects('http://localhost/login');
    }
}
