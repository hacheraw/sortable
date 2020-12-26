# CakePHP Sortable Behavior

Sortable allows to sort rows of a table by using a numeric field.

**TESTED ON CakePHP 4.x**
(this should also work on CakePHP 3.x)

## Installation

* Install the plugin with composer from your CakePHP project's ROOT directory
(where composer.json file is located)

```sh
php composer.phar require hacheraw/sortable
```

* Load the plugin by running command

```sh
bin/cake plugin load Sortable
```

## Configuration and Usage

### Sortable Behavior

Attach the `Sortable` behavior to your table class.

```php
/**
 * @mixin \Sortable\Model\Behavior\SortableBehavior
 */
class ElementsTable extends Table
{
    /**
     * @param array $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Add the behavior to your table
        $this->addBehavior('Sortable.Sortable', [
            'field' => 'name_of_the_field' // default name is `position`
            ]
        );
    }
```

### Controller action example

```php
// ElementsController::action()

    // Move the row to a new position
    $this->Elements->move($row_id, $new_place);

    // Move the row to the top
    $this->Elements->toTop($row_id);

    // Move the row to the bottom
    $this->Elements->toBottom($row_id);
```

## Notes

- The column's type must be numeric (tinyint, smallint, int, double...)
**Note that CakePHP assumes tiniInt(1) as boolean so you have to use tinyInt(2) or greater**

- Be aware that `order` is a MySQL reserved word. You should not use it. Consider using something like `position` or `weight`. Also, if you use `position` as field name you will not need to pass it to the Behavior as that is the default field name.


## Disclaimer

- It is my first public CakePHP Plugin / Composer Package.
- It lacks of unit tests... sorry.
- I have not tested it on tables with a large amount of rows.
- ~~Polite~~ Comments or questions are welcome on Issues section. Even Pull Request!
