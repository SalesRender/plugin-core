<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions\Settings;


use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Core\Actions\FormDataAction;

class GetSettingsDataAction extends FormDataAction
{

    public function __construct()
    {
        parent::__construct(
            fn() => Settings::getForm(),
            fn() => Settings::find()->getData()
        );
    }

}