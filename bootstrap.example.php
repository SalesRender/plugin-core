<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use SalesRender\Plugin\Components\Batch\BatchContainer;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\Actions\Upload\LocalUploadAction;
use SalesRender\Plugin\Core\Actions\Upload\UploadersContainer;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 0. Configure environment variable in .env file, that placed into root of app

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Set permitted file extensions (* for any ext) and max sizes (in bytes). Pass empty array for disable file uploading
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 100 * 1024,       //Max 100 KB for *.jpg file
    'zip' => 10 * 1024 * 1024, //Max 10 MB for *.zip archive
]));

# 4. Configure info about plugin
Info::config(
    new PluginType(PluginType::MACROS),
    fn() => Translator::get('info', 'Plugin name'),
    fn() => Translator::get('info', 'Plugin markdown description'),
    [], //For example, it can be https://github.com/leadvertex/plugin-component-purpose for MACROS, or ["country" => "RU"] for LOGISTIC
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 5. Configure settings form
Settings::setForm(fn(array $context) => new Form($context));

# 6. Configure form autocompletes (or remove this block if dont used)
AutocompleteRegistry::config(function (string $name) {
//    switch ($name) {
//        case 'status': return new StatusAutocomplete();
//        case 'user': return new UserAutocomplete();
//        default: return null;
//    }
});

# 7. Configure batch forms and handler (or remove this block if dont used)
BatchContainer::config(
    function (int $number, array $context) {
//    switch ($number) {
//        case 1: return new Form($context);
//        case 2: return new Form($context);
//        case 3: return new Form($context);
//        default: return null;
//    }
    },
    new BatchHandlerInterface()
);