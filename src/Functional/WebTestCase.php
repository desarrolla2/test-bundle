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

    /**
     * @param string $method
     * @param string $route
     * @param        $path
     */
    protected function addToRequested(string $method, string $route, $path)
    {
        $cache = $this->getCache();
        $requested = $cache->get($this->getCacheKey());
        if (!$requested) {
            $requested = [];
        }
        if (!array_key_exists($route, $requested)) {
            $requested[$route] = [];
        }
        $hash = sprintf('%s_%s', $method, $path);
        $requested[$route][$hash] = ['method' => $method, 'route' => $route, 'path' => $path];
        $cache->set($this->getCacheKey(), $requested, 60);
    }

    /**
     * @param Response $response
     * @param string   $route
     */
    protected function assertOk(Response $response, string $route)
    {
        $this->assertStatus($response, Response::HTTP_OK, $route);
    }

    /**
     * @param Response $response
     * @param string   $route
     */
    protected function assertRedirect(Response $response, string $route)
    {
        $this->assertStatus($response, Response::HTTP_FOUND, $route);
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
     * @param string   $contentType
     * @param string   $route
     */
    protected function assertResponseContentType(Response $response, string $contentType, string $route)
    {
        $this->assertSame(
            $contentType,
            $response->headers->get('Content-Type'),
            sprintf('Failed on route "%s".', $route)
        );
    }

    /**
     * @param Response $response
     * @param string   $route
     */
    protected function assertResponseIsHtml(Response $response, string $route)
    {
        $this->assertResponseContentType($response, 'text/html; charset=UTF-8', $route);
    }

    /**
     * @param Response $response
     * @param string   $route
     */
    protected function assertResponseIsJson(Response $response, string $route)
    {
        $this->assertResponseContentType($response, 'application/json', $route);
    }

    /**
     * @param Response $response
     * @param int      $status
     * @param string   $route
     */
    protected function assertStatus(Response $response, int $status, string $route)
    {
        $this->assertSame($status, $response->getStatusCode(), sprintf('Failed on route "%s".', $route));
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
     * @return Client
     */
    protected function getClient()
    {
        return static::createClient();
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
     * @param Response $response
     * @param string   $name
     * @return bool|string
     */
    protected function getCsrfTokenFromResponse(Response $response, $name = 'form')
    {
        $regex = sprintf('#%s\[\_token\]\"\svalue\=\"[\w\d\-]+\"#', $name);
        preg_match($regex, $response->getContent(), $matches);
        if (!$matches) {
            return '';
        }

        $regex = sprintf('#%s\[\_token\]\"\svalue\=\"#', $name);

        $match = preg_replace($regex, '', $matches[0]);

        return substr($match, 0, strlen($match) - 1);
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
     * @return string
     */
    protected static function getKernelClass()
    {
        require_once __DIR__.'/../../../../../app/AppKernel.php';

        return 'AppKernel';
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
     * @param string $parameter
     * @return mixed
     */
    protected function getParameter(string $parameter)
    {
        $container = $this->getContainer();

        return $container->getParameter($parameter);
    }

    /**
     * @return User
     */
    protected function getUser(string $email)
    {
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        return $em->getRepository($this->getUserEntity())->findOneBy(['email' => $email]);
    }

    abstract protected function getUserEntity();

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
    }

    /**
     * @param Client $client
     * @param string $method
     * @param string $route
     * @param array  $routeParameters
     * @param array  $requestParameters
     * @return null|Response
     */
    protected function request(
        Client $client,
        string $method = 'GET',
        string $route,
        array $routeParameters = [],
        array $requestParameters = []
    ) {
        $path = $this->generateRoute($route, $routeParameters);
        $this->addToRequested($method, $route, $path);
        $client->request($method, $path, $requestParameters);
        $response = $client->getResponse();

        return $response;
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
        $this->assertOk($response, $route);

        return $response;
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
        $this->assertResponseIsHtml($response, $route);

        return $response;
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
        array $parameters = []
    ) {
        $response = $this->requestAndAssertOk($client, $method, $route, $parameters);
        $this->assertResponseIsJson($response, $route);

        return $response;
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
        $this->assertRedirect($response, $route);

        return $response;
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     */
    protected function requestGetAndPost(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = []
    ) {
        $response = $this->requestAndAssertOkAndHtml(
            $client,
            'GET',
            $route,
            $routeParams
        );
        $token = $this->getCsrfTokenFromResponse($response, $formName);


        return $this->request(
            $client,
            'POST',
            $route,
            $routeParams,
            [$formName => array_merge(['_token' => $token], $formParams)]
        );
    }

    /**
     * @param Client $client
     * @param string $route
     * @param array  $routeParams
     * @param string $formName
     * @param array  $formParams
     */
    protected function requestGetAndPostAndAssertRedirect(
        Client $client,
        string $route,
        array $routeParams = [],
        string $formName = 'form',
        array $formParams = []
    ) {
        $response = $this->requestGetAndPost($client, $route, $routeParams, $formName, $formParams);
        file_put_contents('/home/daniel/Projects/crm.she/salida.html', $response->getContent());
        $this->assertRedirect($response, $route);
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
