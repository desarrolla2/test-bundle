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

use Desarrolla2\Cache\Cache;
use Desarrolla2\TestBundle\Model\Key;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @coversNothing
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /** @var Container */
    protected $container;

    /** @var ConsoleOutput */
    protected $output;

    /** @var array */
    protected $requested = [];

    private $lastest = ['route' => null, 'method' => null, 'path' => null];

    /**
     * @return string
     */
    protected static function getKernelClass()
    {
        require_once __DIR__.'/../../../../../app/AppKernel.php';

        return 'AppKernel';
    }

    /**
     * @param Response $response
     * @param string   $isContained
     */
    protected function assertResponseContains(Response $response, string $isContained)
    {
        $this->assertRegexp(sprintf('#%s#', preg_quote($isContained)), $response->getContent());
    }

    /**
     * @param Response $response
     */
    protected function assertResponseIsXml(Response $response)
    {
        $this->assertResponseContentType($response, 'text/xml');
    }

    /**
     * @param Response $response
     * @param string   $contentType
     */
    protected function assertResponseContentType(Response $response, string $contentType)
    {
        $this->assertSame(
            $contentType,
            $response->headers->get('Content-Type'),
            $this->getFailedMessage()
        );
    }

    /**
     * @return string
     */
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

    /**
     * @return string
     */
    protected function getOutputFileName()
    {
        return sprintf('%s/test.latest.ouput', $this->getParameter('kernel.logs_dir'));
    }

    /**
     * @param string $parameter
     * @return mixed
     */
    protected function getParameter(string $parameter)
    {
        $container = $this->getContainer();

        return $container->getParameter($parameter);
    }

    /**
     * @return Container|\Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function getContainer()
    {
        if (!$this->container) {
            $kernel = static::bootKernel([]);
            $this->container = $kernel->getContainer();
        }

        return $this->container;
    }

    /**
     * @return Client
     */
    protected function getClient()
    {
        return static::createClient();
    }

    /**
     * @param string $entityName
     * @return null|object
     */
    protected function getLastUpdatedEntity(string $entityName)
    {
        $em = $this->getEntityManager();

        return $em->getRepository($entityName)->findOneBy(
            [],
            ['updatedAt' => 'DESC']
        );
    }

    /**
     * @return \Doctrine\ORM\EntityManager|object
     */
    protected function getEntityManager()
    {
        $container = $this->getContainer();

        return $container->get('doctrine.orm.entity_manager');
    }

    /**
     * @return ConsoleOutput
     */
    protected function getOutput()
    {
        if (!$this->output) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getRandomEmail()
    {
        return sprintf('%s@devtia.com', bin2hex(random_bytes(10)));
    }

    /**
     * @param $client
     * @return bool|string
     */
    protected function getRandomPdfFile()
    {
        $source = realpath(sprintf('%s/../../data/file.pdf', __DIR__));
        $target = sprintf('%s/%s.pdf', sys_get_temp_dir(), uniqid('desarrolla2_test_bundle_', true));
        exec(sprintf('cp %s %s', $source, $target));

        return $target;
    }

    /**
     * @param $client
     * @return bool|string
     */
    protected function getRandomPngFile()
    {
        return realpath(sprintf('%s/../../data/file.png', __DIR__));
    }

    /**
     * @param int $limit
     * @return string
     */
    protected function getRandomString(int $limit = 75): string
    {
        $now = new \DateTime();
        $stack = debug_backtrace(0, 1);
        $first = reset($stack);
        $file = str_replace($this->container->getParameter('kernel.project_dir'), '', $first['file']);
        $name = sprintf(
            '"%s:%d" at "%s"',
            $file,
            $first['line'],
            $now->format('d/m/Y H:i')
        );
        if (strlen($name) > $limit) {
            return trim(substr(sprintf('...%s', $name), -$limit));
        }

        return $name;
    }

    /**
     * @param Client $client
     * @param string $username
     * @param array  $roles
     */
    protected function logIn(Client $client, string $email, array $roles = [])
    {
        $container = $this->getContainer();
        $session = $container->get('session');
        $user = $this->getUser($email);
        $firewallContext = 'main';
        $token = new UsernamePasswordToken($user, null, $firewallContext, $roles);
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);

        return $user;
    }

    /**
     * @return User
     */
    protected function getUser(string $email)
    {
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository($this->getUserEntity())->findOneBy(['email' => $email]);
        if (!$user) {
            throw  new \InvalidArgumentException(
                sprintf('"%s" with email "%s" not found', $this->getUserEntity(), $email)
            );
        }

        return $user;
    }

    abstract protected function getUserEntity();

    /**
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function persist($entity)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    /**
     * @param Client $client
     * @param string $method
     * @param string $route
     * @param array  $parameters
     * @return null|Response
     */
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

    /**
     * @param Client      $client
     * @param string      $method
     * @param string      $route
     * @param array       $routeParameters
     * @param array       $requestParameters
     * @param array       $requestFiles
     * @param array       $requestServer
     * @param string|null $requestContent
     * @return null|Response
     */
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

    /**
     * @param string $routeName
     * @param array  $params
     *
     * @return mixed
     */
    protected function generateRoute($routeName, array $params = [])
    {
        return $this->getContainer()->get('router')->generate($routeName, $params);
    }

    /**
     * @param string $method
     * @param string $route
     * @param string $path
     * @param float  $time
     */
    protected function addToRequested(string $method, string $route, string $path, float $time)
    {
        $cache = $this->getCache();
        $requested = $cache->get($this->getCacheKey());
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
        $cache->set($this->getCacheKey(), $requested, $this->getCacheTtl());

        $this->lastest = ['route' => $route, 'method' => $method, 'path' => $path];
    }

    /**
     * @return Cache
     */
    protected function getCache()
    {
        return $this->getContainer()->get('desarrolla2.cache');
    }

    /**
     * @return string
     */
    protected static function getCacheKey(): string
    {
        return Key::CACHE;
    }

    /**
     * @return float|int
     */
    protected function getCacheTtl()
    {
        return 60;
    }

    /**
     * @param Response $response
     * @return bool|void
     */
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

    /**
     * @param Response $response
     * @return string
     */
    protected function getOutputFileExtension(Response $response)
    {
        if ($response->headers->get('Content-Type') == 'application/json') {
            return 'json';
        }

        return 'html';
    }

    /**
     * @param Response $response
     * @param int      $status
     */
    protected function assertStatus(Response $response, int $status)
    {
        $this->assertSame($status, $response->getStatusCode(), $this->getFailedMessage());
    }

    /**
     * @param Client $client
     * @param string $method
     * @param string $route
     * @param array  $parameters
     * @return null|Response
     */
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

    /**
     * @param Client $client
     * @param string $method
     * @param string $route
     * @param array  $parameters
     * @return null|Response
     */
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

    /**
     * @param Response $response
     */
    protected function assertOk(Response $response)
    {
        $this->assertStatus($response, Response::HTTP_OK);
    }

    /**
     * @param Response $response
     */
    protected function assertResponseIsJson(Response $response)
    {
        $this->assertResponseContentType($response, 'application/json');
    }

    /**
     * @param Client $client
     * @param string $method
     * @param string $route
     * @param array  $routeParameters
     * @param array  $parameters
     * @return null|Response
     */
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

    /**
     * @param Response $response
     */
    protected function assertRedirect(Response $response)
    {
        $this->assertStatus($response, Response::HTTP_FOUND);
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     * @param array  $fileParams
     */
    protected function requestGetAndPostAndAssertOkAndHtml(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = []
    ) {
        $response = $this->requestGetAndPost($client, $route, $routeParams, $formName, $formParams, $fileParams);
        $this->assertOk($response);
        $this->assertResponseIsHtml($response);
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     * @param array  $fileParams
     * @return null|Response
     */
    protected function requestGetAndPost(
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

        return $this->request(
            $client,
            'POST',
            $route,
            $routeParams,
            [$formName => $formParams],
            $fileParams
        );
    }

    /**
     * @param Client $client
     * @param string $method
     * @param string $route
     * @param array  $parameters
     * @return null|Response
     */
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

    /**
     * @param Response $response
     */
    protected function assertResponseIsHtml(Response $response)
    {
        $this->assertResponseContentType($response, 'text/html; charset=UTF-8');
    }

    /**
     * @param Response $response
     * @param string   $name
     * @return bool|string
     */
    protected function getFormNameFromResponse(Response $response, $name = 'form')
    {
        $regex = sprintf('#\"[\w\d\-]+\[\_token\]#', $name);
        preg_match($regex, $response->getContent(), $matches);
        if (!$matches) {
            return '';
        }

        return str_replace(['[_token]', '"'], ['', ''], $matches[0]);
    }

    /**
     * @param Response $response
     * @param string   $name
     * @return bool|string
     */
    protected function getCsrfTokenValueFromResponse(Response $response, $name = 'form')
    {
        $regex = sprintf('#%s\[\_token\]\"[\s\w\=\-\"]+value\=\"[\w\d\-]+\"#', $name);
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

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     * @param array  $fileParams
     */
    protected function requestGetAndPostAndAssertRedirect(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = []
    ) {
        $response = $this->requestGetAndPost($client, $route, $routeParams, $formName, $formParams, $fileParams);
        $this->assertRedirect($response);
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     * @param array  $fileParams
     */
    protected function requestGetAndPostAndAssertRedirectSonata(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = [],
        array $fileParams = []
    ) {
        $response = $this->requestGetAndPostSonata($client, $route, $routeParams, $formName, $formParams, $fileParams);
        $this->assertRedirect($response);
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     * @param array  $fileParams
     * @return null|Response
     */
    protected function requestGetAndPostSonata(
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

        return $this->request(
            $client,
            'POST',
            $route,
            $routeParams,
            [$formName => $formParams, 'uniqid' => $formName, 'btn_create_and_edit' => ''],
            $fileParams
        );
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array $routeParams
     * @param string $formName
     * @param array $formParams
     * @param array $fileParams
     */
    protected function requestGetPostAndDownloadCsv(
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
        $this->assertResponseIsCsv($response);

        return $response;
    }

    /**
     * @param Response $response
     */
    protected function assertResponseIsCsv(Response $response)
    {
        $this->assertResponseContentType($response, 'text/csv; charset=utf-8');
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     */
    protected function requestDownloadPdf(
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
        $this->assertResponseIsPdf($response);

        return $response;
    }

    /**
     * @param Response $response
     */
    protected function assertResponseIsPdf(Response $response)
    {
        $this->assertResponseContentType($response, 'application/pdf');
    }

    protected function tearDown()
    {
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }
}
