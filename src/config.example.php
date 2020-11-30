<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use Leadvertex\Plugin\Components\Batch\BatchFormRegistry;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Developer\Developer;
use Leadvertex\Plugin\Components\Form\Components\AutocompleteRegistry;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Settings\SettingsForm;
use Leadvertex\Plugin\Components\Translations\Translator;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 0. Configure environment variable in file .env, that placed into root of app
/*
LV_PLUGIN_DEBUG=1
LV_PLUGIN_PHP_BINARY=php
LV_PLUGIN_QUEUE_LIMIT=1
LV_PLUGIN_SELF_URI=http://plugin/
*/

# 1. Configure DB
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Configure info about plugin developer (about you or your company)
Developer::config(
    'Tony Stark',
    'support@starkindustriex.com',
    'starkindustriex.com',
);

# 4. Configure settings form
SettingsForm::config(
    function () { return Translator::get('settings', 'title'); },
    function () { return Translator::get('settings', 'description'); },
    function () { return []; },
    function () { return Translator::get('settings', 'button'); }
);

# 5. Configure batch forms (if you plugin use batches, or remove this block)
BatchFormRegistry::config(function (int $number) {
//    switch ($number) {
//        case 1: return new Form();
//        case 2: return new Form();
//        case 3: return new Form();
//    }
});

# 6. Configure form autocompletes (if some plugin forms use autocompletes, or remove this block)
AutocompleteRegistry::config(function (string $name) {
//    switch ($name) {
//        case 'status': return new StatusAutocomplete();
//        case 'user': return new UserAutocomplete();
//    }
});