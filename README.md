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

Each time a request is executed, the result of the response is stored in `desarrolla2.request.latest`. 
If you have phpunit configured to stop when it fails, here you will have in HTML format the response of the request 
that failed, which will allow you to find the error more quickly.

The bundle also brings the command `php bin / console phpunit: statistics --env = test` if you execute it it will show 
you:

1. A summary of the time taken to execute the tests, how many requests were made, average per request and number of 
routes not tested.

```txt
╰─$ php bin/console phpunit:statistics --env=test
+--------------------------+----------+------------+
| name                     | number   | percentage |
+--------------------------+----------+------------+
| Total execution time     | 1m23s    |            |
| Total requests           | 848      |            |
| Average time per request | 200ms    |            |
| Total routes             | 538      |            |
| Tested routes            | 470      | (87.36%)   |
| Pending routes           | 68       | (12.64%)   |
+--------------------------+----------+------------+
```

2. In the file `desarrolla2.routes.tested.txt` the detail of all the requests that were made, and how long it took to 
execute each one.

```txt
╰─$ cat var/logs/desarrolla2.routes.tested.txt
001. GET _api.activity.index ~0.152
   - /admin/api/activity/1 0.152
002. GET _api.contact.email ~0.168
   - /admin/api/contact/115148/email/daniel@devtia.com 0.168
003. GET _api.contact.phone ~0.128
   - /admin/api/contact/115148/phone/653965048 0.128
004. GET _api.service.index ~0.125
   - /admin/api/service/ 0.125
005. GET _api.sidebar.toggle ~0.091
   - /admin/api/sidebar/toggle 0.091
006. GET _app.activity.index ~2.533
[...]
```

3. In the file `desarrolla2.tested.pending.txt` the detail of the routes that have not been tested.

```txt
╰─$ cat var/logs/desarrolla2.routes.pending.txt
01. GET  _app.report.billing.payment
02. POST _app.transport.locator.view
03. GET  _app_admin_billing_invoice_batch
04. GET  _app_admin_contact_batch
05. GET  _app_admin_file_file_list
06. GET  _app_admin_participant_batch
07. GET  _public.contract.back
08. GET  _public.file.download
09. GET  _public.proposal.view
10. GET  _student.default.switch
[...]
```
4. In the file `desarrolla2.classes.profile.txt` the detail of the tests executed, and how long it took to execute each one

```txt
╰─$ cat var/logs/desarrolla2.classes.profile.txt
PublicBundle\Functional\Api\ProvinceTest: 241ms
 - testIndex: 241ms
AppBundle\Functional\FosUserTest: 438ms
 - testDefault: 438ms
AppBundle\Functional\Admin\MailTest: 499ms
 - testDefault: 499ms
AppBundle\Functional\Admin\ConfigurationTest: 712ms
 - testDefault: 712ms
AppBundle\Functional\DefaultTest: 729ms
 - testIndex: 729ms
AppBundle\Functional\Admin\LanguageTest: 759ms
 - testDefault: 759ms
AppBundle\Functional\File\FileTest: 775ms
 - testCategory: 775ms
PublicBundle\Functional\Api\HealthCheckTest: 779ms
 - testIndex: 156ms
 - testSwagger: 623ms
[...]
```

## Contact

You can contact with me on [@desarrolla2](https://twitter.com/desarrolla2). 

