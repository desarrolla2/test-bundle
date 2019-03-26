# TestBundle

This bundle allows you to perform functional tests quickly and easily with symfony.

## Instalation

It is best installed it through [packagist](http://packagist.org/packages/desarrolla2/test-bundle) 
by including `desarrolla2/test-bundle` in your project composer.json require:

``` json
    "require": {
        // ...
        "desarrolla2/test-bundle":  "*"
    }
```

## Usage

You just have to extend WebTestCase to start using the power of this bundle.

``` php
use Desarrolla2\TestBundle\Functional\WebTestCase 

class ActivityTest extends WebTestCase

```

## Examples

Here are some examples

1. make a request and check that the answer is a 200 in html

``` php
class ActivityTest extends WebTestCase
{
    public function testIndex()
    {
        $client = $this->getClient();
        $user = $this->logIn($client, 'daniel@devtia.com', $this->getBackendRoles());

        $this->requestAndAssertOkAndHtml(
            $client,
            'GET',
            '_app.activity.index'
        );
    }        
}
```    

2. complete a form, send it and check that a 301 returns. We always do redirect, when a form has passed the validation.

``` php
class ProfileTest extends WebTestCase
{
    public function testChangePassword()
    {
        $client = $this->getClient();
        $user = $this->logIn($client, 'daniel@devtia.com', $this->getBackendRoles());

        $this->requestGetAndPostAndAssertRedirect(
            $client,
            '_app.profile.password',
            [],
            'form',
            ['password' => ['first' => 'changeM3', 'second' => 'changeM3']]
        );
    }        
}
```    

As you can see, doing functional tests with this bundle is really easy. Go ahead and discover all the utilities that it 
brings

## Reporting

Each time a request is executed, the result of the response is stored in `develop2.request.latest`. 
If you have phpunit configured to stop when it fails, here you will have in HTML format the response of the request 
that failed, which will allow you to find the error more quickly.

The bundle also brings the command `php bin / console phpunit: statistics --env = test` if you execute it it will show 
you:

1. A summary of the time taken to execute the tests, how many requests were made, average per request and number of 
routes not tested
2. In the file `develop2.routes.tested.txt` the detail of all the requests that were made, and how long it took to 
execute each one.
3. In the file `develop2.tested.pending.txt` the detail of the routes that have not been tested.
4. In the file `develop2.classes.profile.txt` the detail of the tests executed, and how long it took to execute each one

## Contact

You can contact with me on [@desarrolla2](https://twitter.com/desarrolla2). 

