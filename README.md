# Symfony view-bundle

The goal of the bundle is to provide a convenient way of transforming PHP objects into `JSON` responses while using it with the API.
It's mainly used with `Typescript`, but could be used with other client languages as well.

```
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

```
use \Dev\ViewBundle\Annotation\Type;

class UserView extends BindView
{
    public Uuid $id;
    public string|null $firstName = null;
    public string|null $lastName = null;
    public int $notBoundField;
    #[Type(AnswerView::class)]
    public IterableView $answers;

    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->notBoundField = $user->getNotBoundField();
    }
}

```

# Installation

```
composer install symfony-orchestra/view-bundle:7.0.*
```

# Supported internal views

`\Dev\ViewBundle\View\ResponseView` - the main view that can be considered as a response. Contains required headers that can be overridden.

`\Dev\ViewBundle\View\DataView` - the inherited view of the `ResponseView`, that wraps all the data into `data` JSON key.

`\Dev\ViewBundle\View\BindView` - the helper view that maps the properties of the underlined object to the view as one to one. The most powerful one.

`\Dev\ViewBundle\View\IterableView` - the view for the iterable objects, can be used together with the `\Dev\ViewBundle\Annotation\Type`
attribute to simplify the usage. In this case the underlined iterable objects will be automatically constructed based on the configured
type. 