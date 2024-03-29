<?php

/*
 * This file is part of the desarrolla2 test bundle package
 *
 * Copyright (c) 2017-2018 Devtia Soluciones
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Daniel González <daniel@devtia.com>
 */

namespace Desarrolla2\TestBundle\Functional;

use Desarrolla2\TestBundle\Model\Key;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

/**
 * @coversNothing
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /** @var ConsoleOutput */
    protected $output;

    /** @var array */
    protected $requested = [];

    /** @var float */
    protected $startAt = 0.0;

    /** @var array */
    private $lastest = ['route' => null, 'method' => null, 'path' => null];

    protected function executeCommand(string $commandName, array $options = []): string
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find($commandName);
        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester->getDisplay();
    }

    protected function addToRequested(string $method, string $route, string $path, float $time)
    {
        $cache = $this->getCache();
        $requested = $cache->get($this->getCacheKeyForRoutes());
        if (!$requested) {
            $requested = [];
        }
        if ($method == 'HEAD') {
            $method = 'GET';
        }
        $key = sprintf('%s%s', $method, $route);
        if (!array_key_exists($key, $requested)) {
            $requested[$key] = ['method' => $method, 'route' => $route, 'paths' => [], 'time' => 0];
        }
        $requested[$key]['time'] += $time;
        $requested[$key]['paths'][] = ['path' => $path, 'time' => $time];
        $cache->set($this->getCacheKeyForRoutes(), $requested, $this->getCacheTtl());

        $this->lastest = ['route' => $route, 'method' => $method, 'path' => $path];
    }

    protected function assertOk(Response $response)
    {
        $this->assertStatus($response, Response::HTTP_OK);
    }

    protected function assertRedirect(Response $response)
    {
        $this->assertStatus($response, Response::HTTP_FOUND);
    }

    protected function assertResponseContains(Response $response, string $isContained)
    {
        $this->assertMatchesRegularExpression(sprintf('#%s#', preg_quote($isContained)), $response->getContent());
    }

    protected function assertResponseContentType(Response $response, string $contentType)
    {
        $this->assertSame(
            $contentType,
            $response->headers->get('Content-Type'),
            $this->getFailedMessage()
        );
    }

    protected function assertResponseIsCsv(Response $response)
    {
        $this->assertResponseContentType($response, 'text/csv; charset=utf-8');
    }

    protected function assertResponseIsHtml(Response $response)
    {
        $this->assertResponseContentType($response, 'text/html; charset=UTF-8');
    }

    protected function assertResponseIsJson(Response $response)
    {
        $this->assertResponseContentType($response, 'application/json');
    }

    protected function assertResponseIsPdf(Response $response)
    {
        $this->assertResponseContentType($response, 'application/pdf');
    }

    protected function assertResponseIsXml(Response $response)
    {
        $this->assertResponseContentType($response, 'text/xml');
    }

    protected function assertStatus(Response $response, int $status)
    {
        $this->assertSame($status, $response->getStatusCode(), $this->getFailedMessage());
    }

    protected function generateRoute($routeName, array $params = [])
    {
        return $this->get('router')->generate($routeName, $params);
    }

    protected function get(string $serviceName)
    {
        return self::$container->get($serviceName);
    }

    protected function getCache()
    {
        return $this->get('desarrolla2.cache');
    }

    protected static function getCacheKeyForClasses(): string
    {
        return Key::CLASSES;
    }

    protected static function getCacheKeyForRoutes(): string
    {
        return Key::ROUTES;
    }

    protected function getCacheTtl()
    {
        return 60;
    }

    protected function getCsrfTokenValueFromResponse(Response $response, string $name = 'form', string $token = '_token')
    {
        $regex = sprintf('#%s\[\%s\]\"[\s\w\=\-\"]+value\=\"[\w\d\-]+\"#', $name, $token);
        if ($name == '') {
            $regex = sprintf('#\"%s\"[\s\w\=\-\"]+value\=\"[\w\d\-]+\"#', $token);
        }
        preg_match($regex, $response->getContent(), $match1);
        if (!$match1) {
            return '';
        }

        $regex = '#value\=\"[\w\d\-]+\"#';
        preg_match($regex, $match1[0], $match2);
        if (!$match2) {
            return '';
        }

        return str_replace(['value=', '"'], ['', ''], $match2[0]);
    }

    protected function getEntityManager()
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    protected function getFailedMessage()
    {
        if (!is_array($this->lastest)) {
            return '';
        }

        return sprintf(
            'Failed executing "%s" "%s" with route "%s". You can find last response in %s._format.',
            $this->lastest['method'],
            $this->lastest['path'],
            $this->lastest['route'],
            $this->getOutputFileName()
        );
    }

    protected function getFormNameFromResponse(Response $response, $name = 'form')
    {
        $regex = sprintf('#\"[\w\d\-]+\[\_token\]#', $name);
        preg_match($regex, $response->getContent(), $matches);
        if (!$matches) {
            return '';
        }

        return str_replace(['[_token]', '"'], ['', ''], $matches[0]);
    }

    protected function getLastCreatedEntity(string $entityName, array $criteria = [])
    {
        $em = $this->getEntityManager();

        return $em->getRepository($entityName)->findOneBy(
            array_merge([], $criteria),
            ['createdAt' => 'DESC']
        );
    }

    protected function getLastUpdatedEntity(string $entityName, array $criteria = [])
    {
        $em = $this->getEntityManager();

        return $em->getRepository($entityName)->findOneBy(
            array_merge([], $criteria),
            ['updatedAt' => 'DESC']
        );
    }

    protected function getOutput()
    {
        if (!$this->output) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    protected function getOutputFileExtension(Response $response)
    {
        if ($response->headers->get('Content-Type') == 'application/json') {
            return 'json';
        }

        return 'html';
    }

    protected function getOutputFileName()
    {
        return sprintf('%s/desarrolla2.request.latest', $this->getParameter('kernel.logs_dir'));
    }

    protected function getParameter(string $parameter)
    {
        return self::$container->getParameter($parameter);
    }

    protected function getRandomEmail()
    {
        return sprintf('%s@devtia.com', bin2hex(random_bytes(10)));
    }

    protected function getRandomItem(array $items)
    {
        $items = array_values($items);

        return $items[array_rand($items)];
    }

    protected function getRandomPdfFile()
    {
        $source = realpath(sprintf('%s/../../data/file.pdf', __DIR__));
        $target = sprintf('%s/%s.pdf', sys_get_temp_dir(), uniqid('desarrolla2_test_bundle_', true));
        exec(sprintf('cp %s %s', $source, $target));

        return $target;
    }

    protected function getRandomPngFile()
    {
        $source = realpath(sprintf('%s/../../data/file.png', __DIR__));
        $target = sprintf('%s/%s.png', sys_get_temp_dir(), uniqid('desarrolla2_test_bundle_', true));
        exec(sprintf('cp %s %s', $source, $target));

        return $target;
    }

    protected function getRandomString(int $limit = 75): string
    {
        $now = new \DateTime();
        $stack = debug_backtrace(0, 1);
        $first = reset($stack);
        $file = str_replace($this->getParameter('kernel.project_dir'), '', $first['file']);
        $name = sprintf(
            '"%s:%d" at "%s"',
            $file,
            $first['line'],
            $now->format('d/m/Y H:i:s')
        );
        if (strlen($name) > $limit) {
            return trim(substr(sprintf('...%s', $name), -$limit));
        }

        return $name;
    }

    protected function getUser(string $email): UserInterface
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $user = $em->getRepository($this->getUserEntity())->findOneBy(['email' => $email]);
        if (!$user) {
            throw  new \InvalidArgumentException(
                sprintf('"%s" with email "%s" not found', $this->getUserEntity(), $email)
            );
        }

        return $user;
    }

    abstract protected function getUserEntity();

    protected function handleResponse(Response $response)
    {
        file_put_contents(
            sprintf(
                '%s.%s',
                $this->getOutputFileName(),
                $this->getOutputFileExtension($response)
            ),
            $response->getContent()
        );
    }

    protected function logIn(Client $client, string $email, $firewallContext = 'main'): void
    {
        $user = $this->getUser($email);
        $token = new PostAuthenticationGuardToken($user, $firewallContext, $user->getRoles());

        /** @var SessionInterface $session */
        $session = $this->get('session');
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    protected function request(
        Client $client,
        string $method = 'GET',
        string $route,
        array $routeParameters = [],
        array $requestParameters = [],
        array $requestFiles = [],
        array $requestServer = [],
        string $requestContent = null
    ) {
        $path = $this->generateRoute($route, $routeParameters);
        $start = microtime(true);
        $client->request($method, $path, $requestParameters, $requestFiles, $requestServer, $requestContent);
        $response = $client->getResponse();
        $time = round(microtime(true) - $start, 3);
        $this->addToRequested($method, $route, $path, $time);
        $this->handleResponse($response);

        return $response;
    }

    protected function requestAndAssertNotFound(
        Client $client,
        string $method = 'GET',
        string $route,
        array $parameters = []
    ) {
        $response = $this->request($client, $method, $route, $parameters);
        $this->assertStatus($response, Response::HTTP_NOT_FOUND, $route);

        return $response;
    }

    protected function requestAndAssertOk(
        Client $client,
        string $method = 'GET',
        string $route,
        array $routeParameters = [],
        array $parameters = []
    ) {
        $response = $this->request($client, $method, $route, $routeParameters, $parameters);
        $this->assertOk($response);

        return $response;
    }

    protected function requestAndAssertOkAndHtml(
        Client $client,
        string $method = 'GET',
        string $route,
        array $routeParameters = [],
        array $parameters = []
    ) {
        $response = $this->requestAndAssertOk($client, $method, $route, $routeParameters, $parameters);
        $this->assertResponseIsHtml($response);

        return $response;
    }

    protected function requestAndAssertOkAndJson(
        Client $client,
        string $method = 'GET',
        string $route,
        array $routeParameters = [],
        array $parameters = []
    ) {
        $response = $this->requestAndAssertOk($client, $method, $route, $routeParameters, $parameters);
        $this->assertResponseIsJson($response);

        return $response;
    }

    protected function requestAndAssertRedirect(
        Client $client,
        string $method = 'GET',
        string $route,
        array $routeParameters = [],
        array $parameters = []
    ) {
        $response = $this->request($client, $method, $route, $routeParameters, $parameters);
        $this->assertRedirect($response);

        return $response;
    }

    protected function requestDownload(
        Client $client,
        string $route,
        array $routeParams = []
    ) {
        ob_start();
        $response = $this->request(
            $client,
            'GET',
            $route,
            $routeParams
        );
        ob_end_clean();

        return $response;
    }

    protected function requestDownloadAndAssertOk(Client $client, string $route, array $routeParams = [])
    {
        $response = $this->requestDownload($client, $route, $routeParams);
        $this->assertOk($response);

        return $response;
    }

    protected function requestGetAndPost(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = [],
        bool $csrfProtection = true
    ) {
        $response = $this->requestAndAssertOkAndHtml(
            $client,
            'GET',
            $route,
            $routeParams
        );
        if ($formName == '') {
            $formName = $this->getFormNameFromResponse($response);
        }

        if ($csrfProtection) {
            $formParams['_token'] = $this->getCsrfTokenValueFromResponse($response, $formName);
        }

        return $this->request(
            $client,
            'POST',
            $route,
            $routeParams,
            [$formName => $formParams],
            $fileParams
        );
    }

    protected function requestGetAndPostAndAssertOkAndHtml(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = [],
        bool $csrfProtection = true
    ) {
        $response = $this->requestGetAndPost(
            $client,
            $route,
            $routeParams,
            $formName,
            $formParams,
            $fileParams,
            $csrfProtection
        );
        $this->assertOk($response);
        $this->assertResponseIsHtml($response);
    }

    protected function requestGetAndPostAndAssertRedirect(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = [],
        bool $csrfProtection = true
    ) {
        $response = $this->requestGetAndPost(
            $client,
            $route,
            $routeParams,
            $formName,
            $formParams,
            $fileParams,
            $csrfProtection
        );
        $this->assertRedirect($response);
    }

    protected function requestGetAndPostAndDownload(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = []
    ) {
        $response = $this->requestAndAssertOkAndHtml(
            $client,
            'GET',
            $route,
            $routeParams
        );
        if ($formName == '') {
            $formName = $this->getFormNameFromResponse($response);
        }
        $token = $this->getCsrfTokenValueFromResponse($response, $formName);

        $formParams['_token'] = $token;

        ob_start();
        $response = $this->request(
            $client,
            'POST',
            $route,
            $routeParams,
            [$formName => $formParams],
            $fileParams
        );
        ob_end_clean();

        return $response;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeProfile();
    }

    private function addTimeToExecutedClasses(): void
    {
        $time = round(microtime(true) - $this->startAt, 3);
        $cache = $this->getCache();
        $executed = $cache->get($this->getCacheKeyForClasses());
        if (!$executed) {
            $executed = [];
        }
        $key = get_called_class();
        if (!array_key_exists($key, $executed)) {
            $executed[$key] = ['time' => 0, 'name' => $key, 'tests' => []];
        }
        $executed[$key]['time'] += $time;
        $executed[$key]['tests'][] = ['name' => $this->getName(), 'time' => $time];

        $cache->set($this->getCacheKeyForClasses(), $executed, $this->getCacheTtl());
    }

    private function initializeProfile(): void
    {
        $this->startAt = round(microtime(true), 3);
    }
}
