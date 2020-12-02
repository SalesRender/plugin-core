<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use Leadvertex\Plugin\Components\Batch\BatchFormRegistry;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Form\Components\AutocompleteRegistry;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Info\Developer;
use Leadvertex\Plugin\Components\Info\Info;
use Leadvertex\Plugin\Components\Info\PluginType;
use Leadvertex\Plugin\Components\Settings\SettingsForm;
use Leadvertex\Plugin\Components\Translations\Translator;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 0. Configure environment variable in .env file, that placed into root of app (preferred), or here
//$_ENV['LV_PLUGIN_DEBUG'] = 1;
//$_ENV['LV_PLUGIN_PHP_BINARY'] = 'php';
//$_ENV['LV_PLUGIN_QUEUE_LIMIT'] = 1;
//$_ENV['LV_PLUGIN_SELF_URI'] = 'http://plugin/';

# 1. Configure DB
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Configure info about plugin
Info::config(
    new PluginType(PluginType::MACROS),
    fn() => Translator::get('plugin', 'Plugin name'),
    fn() => Translator::get('plugin', 'Plugin markdown description'),
    [], //For example, it can be https://github.com/leadvertex/plugin-component-purpose for MACROS, or ["country" => "RU"] for LOGISTIC
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 4. Configure settings form
SettingsForm::config(
    fn() => Translator::get('settings', 'title'),
    fn() => Translator::get('settings', 'description'),
    fn() => [],
    fn() => Translator::get('settings', 'button'),
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