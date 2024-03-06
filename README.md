# view-bundle

The `view-bundle` is a simple and highly efficient Symfony bundle designed to replace Symfony Responses when working with the JSON API.

The goal of the bundle is to separate the response views from the app's business logic, making them typed, configurable, and reusable across the app.

As a result, you will have a set of simple `View` classes with an internal hierarchy that is easily understandable by everybody in a team.


# Example

Let's consider the code below as an example.
We have an entity `User` with some fields and with the joined collection of `Image` images.


```php
<?php
declare(strict_types=1);

class User
{
    public Uuid $id;
    public string|null $firstName = null;
    public string|null $lastName = null;
    public iterable $images = [];
}

```
```php
<?php
declare(strict_types=1);

class Image
{
    public Uuid $id;
    private User $user;
    private string $path;
}

```

The possible views for our scenario could be:

```php
<?php
declare(strict_types=1);

use \SymfonyOrchestra\ViewBundle\Attribute\Type;

class UserView extends BindView
{
    public Uuid $id;
    public string|null $firstName;
    public string|null $lastName;
    
    /** It will be transformed into array of ImageViews */
    #[Type(ImageView::class)]
    public IterableView $images;
    
    /** It's a custom property which does not exist in the User class */
    public \DateTimeImmutable $notBoundField;

    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->notBoundField = $user->getCreatedDatetime();
    }
}

```

```php
<?php
declare(strict_types=1);

use \SymfonyOrchestra\ViewBundle\Attribute\Type;

class ImageView extends BindView
{
    public Uuid $id;
    public string $path;
}

```

As a result, the following request for the current user with the name "Andrew", an empty last name, and some pictures of the orchestra 

```php
<?php
declare(strict_types=1);

#[Route('/user/me', methods: ['GET'], priority: 1)]
#[IsGranted('ROLE_USER')]
class GetMeAction extends GetAction
{
    public function __invoke(Request $request): ViewInterface
    {
        return new UserView($this->getUser());
    }
}
```

Will produce the following 200 response

```json
{
  "data": {
    "id": "92c7c4d4-2ce0-4353-a9e2-6a3794c60d8f",
    "firstName": "Andrew",
    "images": [
      {
        "id": "eb9fa57e-3d8f-44c5-80d4-7f33220f1a48",
        "path": "/grand-piano.png"
      },
      {
        "id": "16d01967-9066-4dc9-9d82-028419ba0ed5",
        "path": "/violin.png"
      }
    ],
    "notBoundField": "1685-03-31"
  }
}

```

The response is fully controllable, you can still add different headers to the response using the stack of provided internal View classes (`ResponseView`).

The main payload is placed under the `data` key in the JSON array.

As you can see, the last name is omitted because `null` values were removed from the response to match with `undefined` properties while working with a `Typescript`. 

# Installation

```
composer install symfony-orchestra/view-bundle:7.0.*
```

Add the bundle to `config/bundles.php`
```php
<?php

return [
    /** ... */
    SymfonyOrchestra\ViewBundle\DevViewBundle::class => ['all' => true],
];


```

To make it work your controller should return an object of instance of `SymfonyOrchestra\ViewBundle\View\ViewInterface` instead of `Symfony\Component\HttpFoundation\Response`.

# Cache

The most usable `SymfonyOrchestra\ViewBundle\View\BindView` which maps the properties of the class with the properties of the view comes with the cache support.
See `SymfonyOrchestra\ViewBundle\EventSubscriber\SetVersionSubscriber` for more details.
It uses `Symfony\Component\PropertyAccess\PropertyAccessor::createCache` when the env parameter `APP_DEBUG` is set to `false`.


# Internal views

The bundle comes with the several internal core views.   

### \SymfonyOrchestra\ViewBundle\View\ResponseView 

The main view that can be considered as a response. Contains headers and http status that can be overridden.
See `SymfonyOrchestra\EventSubscriber\ViewSubscriber`.

### \SymfonyOrchestra\ViewBundle\View\DataView 

The inherited view of the `ResponseView`, that wraps all the data into `data` JSON key.
See `SymfonyOrchestra\EventSubscriber\ViewSubscriber`.

### \SymfonyOrchestra\ViewBundle\View\BindView

The helper View that maps the properties of the underlined object to the view as one to one. The most powerful one.
It uses `SymfonyOrchestra\ViewBundle\Utils\BindUtils` internally to map the properties.

```php
class User {
    private int $int;
    private string $string;
    private iterable $collection
}

class UserView extends \SymfonyOrchestra\ViewBundle\View\BindView {
    /** will take all the properties from the User class */
    private int $int;
    private string $string;
    private array $collection
}
```

### \SymfonyOrchestra\ViewBundle\View\IterableView

The view for the iterable objects. 

```php

class GetOptions  extends GetAction
{
    public function __invoke(Request $request): ViewInterface
    {
        $option1 = new Option();
        $option2 = new Option();
        return new \SymfonyOrchestra\ViewBundle\View\IterableView(
            [$option1, $option2],
            OptionView::class,
        );
    }
}

```


It can be used together with the `\SymfonyOrchestra\ViewBundle\Attribute\Type` and `\SymfonyOrchestra\ViewBundle\View\BindView`
attribute to simplify the workflow. In this case the underlined iterable objects will be automatically constructed based on the configured
type. 