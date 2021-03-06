# ORM

Наш ORM – один из самых простых (и тупых) в мире ORM, реализующих шаблон ActiveRecord. Работает быстро, памяти потребляет мало.

Первоначально фреймворк использовал ORM Jelly, позже AutoModeler. Но, как показала практика, в реальных (наших) проектах в 99% случаев 
использовался поразительный минимум возможностей Jelly и это при том, что Jelly гораздо проще, к примеру, чем Doctrine.
 
Т.к. большинство наших проектов — это простые сайты (пусть и большие по масштабам), логика работа с базой данных в большинстве 
случаев очень простая: выбрать запись таблицы A, выбрать N записей из таблицы B по ключу из A...  и всё. Усложнять систему ради
упрощения кода в 1% случаев выглядело не лучшим решением. Можно было и вовсе обойтись без ORM, но опять же, как показала практика,
представление моделей в виде php объектов зачастую удобно и ускоряет разработку. 

Так что был написан очень примитивный ORM. 

### Описание моделей


```php
class User extends ORM {

    public static $table = 'users';
    
    protected $_order_by = ['name' => 'ASC'];
    
    protected $_data = [
        'id' => 0,
        'name' => '',
        'surname' => ''
    ]
}
```

### Простое использование

Создание записи:
```php
$user = new User();
$user->name = 'test';
$user->create();
```

Обновление по аналогии:
```php
$user->update();
```

Работа со свойствами:
```php

$user->name = 'john';
$user->set('name', 'john');
$user->set([
    'name' => 'john',
    'surname' => 'smit'
]);

$name = $user->name;
$name = $user->get('name');


```

Доступ к query builder'у:

```php
$users = User::find()
            ->where('id', '=', 1)
            ->get();
```

Выбор одной записи по primary key (*На самом деле — по полю с именем id*):

```php
User::one(1);
```

Выбор одной записи по набору условий:

```php
User::one([
    ['age', '>', 18],
    ['weight', '<', '70']
);
```

Выбор всех всех записей
```php
User::all();
```


Выбор записей по набору id (*SELECT * FROM users WHERE id IN (1,2,3,4,5)*):
```php
User::all([1,2,3,4,5]);
```

Выбор всех записей удовлетворяющих набору условий:
```php
User::all([
    ['age', '>', 18],
    ['weight', '<', '70']
]);
```



### Связи между моделями

ORM совсем тупой и пока не умеет. На данный момент это реализуется руками в методах модели.