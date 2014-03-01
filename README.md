Yiisset
=======

**Yiisset** представляет собой альтернативный client script для Yii.

**Yiisset** сокращает количество запросов к серверу, уменьшает объем передаваемого трафика и ускоряет загрузку страницы непосредственно в браузере, не меня при этом привычной логики работы с ресурсами приложения.

Проект появился как форк [yii-EClientScript](https://github.com/muayyad-alsadi/yii-EClientScript).

Возможности
-----------
Итак, **Yiisset** позволяет:
* объединять несколько ресурсов в один файл, корректно группируя скрипты по положению в теле страницы, а стили - по атрибуту media;
* минифицировать файлы;
* создавать сжатые копии файлов, что позволяет серверу не тратить время на сжатие файла перед отправкой клиенту;
* удалять инлайновые скрипты из тела страницы, что может быть полезно, если их много;
* использовать параллельную загрузку ресурсов на странице;
* компилировать CoffeeScript.

Установка
---------
В своей работе **Yiisset** использует ряд сторонних инструментов, установить которые не составит никакой сложности для большинства популярных *nix дистрибутивов. Однако, если вы не хотите этого делать, расширение просто отключит те фичи, в которых используются отсутствующие инструменты, и будет нормально работать.

Для простого объединения файлов и параллельной их загрузки достаточно минимального набора, который, скорее всего, у вас уже есть:
* **\*nix** (расширение **не рассчитано** на работу в среде Windows!)
* **PHP** >= **5.3**
* **Yii** >= **1.1.14**

Для всего остального вам понадобится [Node.js](http://nodejs.org/) и его модули:
* [UglifyJS](https://github.com/mishoo/UglifyJS) для минификации скриптов;
* [clean-css](https://github.com/GoalSmashers/clean-css) для минификации стилей;
* [CoffeeScript](http://coffeescript.org) для компиляции CoffeeScript.

Модули можно установить следующей командой:
```
npm install -g coffee uglifyjs clean-css
```

Настройка
---------
Для подключения расширения достаточно заменить компонент:
```php
'clientScript' => array(
    'class' => 'vendors.herroffizier.yiisset.components.EClientScript',
),
```

Настроек по умолчанию будет вполне достаточно для того, чтобы расширение выполняло все свои функции, однако для удобства отладки в своих проектах я использую следующий конфиг:
```php
'clientScript' => array(
    'class' => 'vendors.herroffizier.yiisset.components.EClientScript',
    // объединять ли стили
    'combineCssFiles' => !YII_DEBUG,
    // оптимизировать ли стили
    'optimizeCssFiles' => !YII_DEBUG,
    // объединять ли скрипты
    'combineScriptFiles' => !YII_DEBUG,
    // оптимизировать ли скрипты
    'optimizeScriptFiles' => !YII_DEBUG,
    // сохранять ли сжатые копии файлов
    'saveGzippedCopy' => !YII_DEBUG,
),
```

Наконец, для того, чтобы ресурсы приложения были сгруппированы по номеру ревизии проекта, можно заменить ещё один компонент:
```php
'assetManager' => array(
    'class' => 'vendors.herroffizier.yiisset.components.EAssetManager',
    // при forceCopy = true Yiisset будет обрабатывать ресурсы проекта при каждом запросе
    'forceCopy' => YII_DEBUG,
    // в константе REVISION хранится номер ревизии проекта
    'assetVersion' => !YII_DEBUG ? REVISION : null, 
),
```

