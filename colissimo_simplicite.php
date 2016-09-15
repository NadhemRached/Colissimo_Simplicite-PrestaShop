<?php
/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2015 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Colissimo_simplicite extends CarrierModule
{

    protected $config_form = false;
    protected $config_single_values_keys = false;
    private $api_num_version = '4.0';
    public $url = '';
    private $config = array(
        'name' => 'La Poste - Colissimo Simplicité',
        'id_tax_rules_group' => 0,
        'url' => 'http://www.colissimo.fr/portail_colissimo/suivreResultat.do?parcelnumber=@',
        'active' => true,
        'deleted' => 0,
        'shipping_handling' => false,
        'range_behavior' => 0,
        'is_module' => true,
        'delay' => array(
            'fr' => 'Avec La Poste, Faites-vous livrer là ou vous le souhaitez en France Métropolitaine.',
            'en' => 'Do you deliver wherever you want in France.'),
        'id_zone' => 1,
        'shipping_external' => true,
        'external_module_name' => 'colissimo_simplicite',
        'need_range' => true
    );

    public function __construct()
    {
        $this->name = 'colissimo_simplicite';
        $this->tab = 'shipping_logistics';
        $this->version = '0.0.1';
        $this->author = 'Quadra Informatique';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Colissimo Simplicite');
        $this->description = $this->l('Avec La Poste, Faites-vous livrer là ou vous le souhaitez en France Métropolitaine.');

        $this->confirmUninstall = $this->l('Etes vous sur de vouloir désinstaller le module ?');

        $this->ps_versions_compliancy = array(
            'min' => '1.7',
            'max' => _PS_VERSION_);
        $protocol = 'http://';
        if (Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'))
            $protocol = 'https://';
        $this->url = $protocol.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/'.$this->name.'/validation.php';
        if (self::isInstalled($this->name)) {
            $warning = array();
            $so_carrier = new Carrier(Configuration::get('COLISSIMO_CARRIER_ID'));
            if (Validate::isLoadedObject($so_carrier)) {
                if (!$this->checkZone((int)$so_carrier->id))
                    $warning[] .= $this->l('\'Carrier Zone(s)\'').' ';
                if (!$this->checkGroup((int)$so_carrier->id))
                    $warning[] .= $this->l('\'Carrier Group\'').' ';
                if (!$this->checkRange((int)$so_carrier->id))
                    $warning[] .= $this->l('\'Carrier Range(s)\'').' ';
                if (!$this->checkDelivery((int)$so_carrier->id))
                    $warning[] .= $this->l('\'Carrier price delivery\'').' ';
            }

            //Check config and display warning
            if (!Configuration::get('COLISSIMO_ID'))
                $warning[] .= $this->l('\'Id FO\'').' ';
            if (!Configuration::get('COLISSIMO_KEY'))
                $warning[] .= $this->l('\'Key\'').' ';
            if (!Configuration::get('COLISSIMO_URL'))
                $warning[] .= $this->l('\'Url So\'').' ';

            if (count($warning))
                $this->warning .= implode(' , ', $warning).$this->l('must be configured to use this module correctly').' ';
        }
        $this->config_single_values_keys = array(
            'COLISSIMO_CARRIER_ID',
            'COLISSIMO_CARRIER_ID_SELLER',
            'COLISSIMO_ID',
            'COLISSIMO_USE_FANCYBOX',
            'COLISSIMO_USE_IFRAME',
            'COLISSIMO_KEY',
            'COLISSIMO_URL',
            'COLISSIMO_URL_MOBILE',
            'COLISSIMO_OVERCOST',
            'COLISSIMO_COST_SELLER',
            'COLISSIMO_SELLER_AMOUNT',
            'COLISSIMO_UPG_COUNTRY',
            'COLISSIMO_PREPARATION_TIME',
            'COLISSIMO_CARRIER_ID',
            'COLISSIMO_MIN_COST',
            'COLISSIMO_SUP',
            'COLISSIMO_SUP_URL',
            'COLISSIMO_OVERCOST_TAX',
            'COLISSIMO_PERSONAL_PHONE',
            'COLISSIMO_PERSONAL_ZIP_CODE',
            'COLISSIMO_PERSONAL_QUANTITIES',
            'COLISSIMO_PERSONAL_SIRET',
            'COLISSIMO_PERSONAL_DATA',
        );
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $carrier = $this->addCarrier();
        $this->addZones($carrier);
        $this->addGroups($carrier);
        $this->addRanges($carrier);
        if (!Configuration::updateValue('COLISSIMO_ID', null) ||
            !Configuration::updateValue('COLISSIMO_KEY', null) ||
            !Configuration::updateValue('COLISSIMO_URL', 'ws.colissimo.fr/pudo-fo-frame/storeCall.do') ||
            !Configuration::updateValue('COLISSIMO_URL_MOBILE', 'ws-mobile.colissimo.fr/') ||
            !Configuration::updateValue('COLISSIMO_PREPARATION_TIME', 1) ||
            !Configuration::updateValue('COLISSIMO_OVERCOST', 3.6) ||
            !Configuration::updateValue('COLISSIMO_COST_SELLER', false) ||
            !Configuration::updateValue('COLISSIMO_SELLER_AMOUNT', 0) ||
            !Configuration::updateValue('COLISSIMO_SUP_URL', 'ws.colissimo.fr/supervision-pudo-frame/supervision.jsp') ||
            !Configuration::updateValue('COLISSIMO_SUP', true) ||
            !Configuration::updateValue('COLISSIMO_USE_FANCYBOX', false) ||
            !Configuration::updateValue('COLISSIMO_USE_IFRAME', true) ||
            !$this->registerHook('newOrder') ||
            !$this->registerHook('paymentTop')
        )
            include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('updateCarrier') &&
            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayCarrierList');
    }

    public function uninstall()
    {
        $so_id = (int)Configuration::get('COLISSIMO_CARRIER_ID');
        Configuration::deleteByName('COLISSIMO_ID');
        Configuration::deleteByName('COLISSIMO_USE_FANCYBOX');
        Configuration::deleteByName('COLISSIMO_USE_IFRAME');
        Configuration::deleteByName('COLISSIMO_KEY');
        Configuration::deleteByName('COLISSIMO_URL');
        Configuration::deleteByName('COLISSIMO_URL_MOBILE');
        Configuration::deleteByName('COLISSIMO_OVERCOST');
        Configuration::deleteByName('COLISSIMO_COST_SELLER');
        Configuration::deleteByName('COLISSIMO_SELLER_AMOUNT');
        Configuration::deleteByName('COLISSIMO_UPG_COUNTRY');
        Configuration::deleteByName('COLISSIMO_PREPARATION_TIME');
        Configuration::deleteByName('COLISSIMO_CARRIER_ID');
        Configuration::deleteByName('COLISSIMO_SUP');
        Configuration::deleteByName('COLISSIMO_SUP_URL');
        Configuration::deleteByName('COLISSIMO_OVERCOST_TAX');
        //Configuration::deleteByName('COLISSIMO_PERSONAL_DATA');

        $carrier = new Carrier($so_id);
        $carrier->delete();
        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitColissimo_simpliciteModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'colissimo_version' => $this->version
        ));

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitColissimo_simpliciteModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array(
                array(
                    'form' => $this->getConfigForm()
                )
        ));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $form = array(
            'legend' => array(
                'title' => $this->l('Colissimo Simplicity').' V'.$this->version,
                'icon' => 'icon-cogs',
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            )
        );
        //======================================================================
        // INFO TAB
        $form['tabs']['about'] = $this->l('About Colissimo Informations');

        $form['input'][] = array(
            'tab' => 'about',
            'type' => 'html',
            'name' => 'about',
            'html_content' => $this->context->smarty->fetch($this->local_path.'views/templates/admin/about.tpl'),
        );

        //======================================================================
        // CREDENTIALS TAB
        $form['tabs']['credentials'] = $this->l('Merchant Informations');

        $form['input'][] = array(
            'tab' => 'credentials',
            'type' => 'text',
            'col' => 2,
            'required' => true,
            'label' => $this->l('Phone number'),
            'name' => 'COLISSIMO_PERSONAL_PHONE',
            'desc' => $this->l('Example  0144183004'),
        );
        $form['input'][] = array(
            'tab' => 'credentials',
            'type' => 'text',
            'col' => 2,
            'required' => true,
            'label' => $this->l('Zip code'),
            'name' => 'COLISSIMO_PERSONAL_ZIP_CODE',
            'desc' => $this->l('Example  92300'),
        );
        $form['input'][] = array(
            'tab' => 'credentials',
            'type' => 'select',
            'required' => true,
            'label' => $this->l('Mean number of parcels'),
            'name' => 'COLISSIMO_PERSONAL_QUANTITIES',
            'options' => array(
                'query' => array(
                    array(
                        'id' => '< 250 colis / mois',
                        'name' => $this->l('< 250 colis / mois')
                    ),
                    array(
                        'id' => '> 250 colis / mois',
                        'name' => $this->l('> 250 colis / mois')
                    ),
                ),
                'id' => 'id',
                'name' => 'name'
            ),
        );
        $form['input'][] = array(
            'tab' => 'credentials',
            'type' => 'text',
            'col' => 2,
            'required' => true,
            'label' => $this->l('Siret'),
            'name' => 'COLISSIMO_PERSONAL_SIRET',
            'desc' => $this->l('Siret is 14 number'),
        );
        $form['input'][] = array(
            'tab' => 'credentials',
            'type' => 'checkbox',
            'required' => true,
            'label' => $this->l('Terms & conditions'),
            'name' => 'COLISSIMO_PERSONAL',
            'desc' => $this->l('In case of refusal, you can sent an email at the following address').
            ' : <a style="color: #268ccd;" href="mailto: modules-prestashop@laposte.fr">modules-prestashop@laposte.fr</a>',
            'values' => array(
                'query' => array(
                    array(
                        'id' => 'DATA',
                        'name' => $this->l('I accept that informations concerning the number of parcels are sent to our partner La poste - Colissimo'),
                        'val' => 1
                    ),
                ),
                'id' => 'id',
                'name' => 'name',
            )
        );

        // if not activated, only show the activation file field
        if (!Configuration::get('COLISSIMO_PERSONAL_DATA'))
            return $form;
        else


        //======================================================================
        // GENERAL TAB
            $form['tabs']['general'] = $this->l('Your Colissimo Box');

        $form['input'][] = array(
            'tab' => 'general',
            'col' => 3,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Encryption key'),
            'name' => 'COLISSIMO_KEY',
            'desc' => $this->l('Available in your ').' <a href="https://www.colissimo.entreprise.laposte.fr" target="_blank" >Colissimo Box </a>'.'<br/>'.
            $this->l('by using the menu "Applications > Delivery > Choice of delivery methods" ')
        );
        $form['input'][] = array(
            'tab' => 'general',
            'col' => 3,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Front Office Identifier'),
            'name' => 'COLISSIMO_ID',
            'desc' => $this->l('Available in your ').' <a href="https://www.colissimo.entreprise.laposte.fr" target="_blank" >Colissimo Box </a>'.'<br/>'.
            $this->l('by using the menu "Applications > Delivery > Choice of delivery methods" ')
        );
        $form['input'][] = array(
            'tab' => 'general',
            'col' => 3,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Order Preparation time'),
            'suffix' => $this->l('Day(s)'),
            'name' => 'COLISSIMO_PREPARATION_TIME',
            'desc' => $this->l('Business days from Monday to Friday').
            '<br/>'.$this->l('Must be the same parameter as in your ').
            ' <a href="https://www.colissimo.entreprise.laposte.fr" target="_blank" >Colissimo Box </a>'
        );
        $form['input'][] = array(
            'tab' => 'general',
            'type' => 'html',
            'name' => 'url_note',
            'html_content' => '<hr/><strong>'.$this->l('Please fill in these two addresses in your').
            ' <a href="https://www.colissimo.entreprise.laposte.fr" target="_blank" >Colissimo Box </a></strong><ul>'.
            '<li>'.$this->l('In the "Delivery options selection page"').'</li>'.
            '<li>'.$this->l('In the "Delivery options selection page (mobile version)"').'</li></ul>',
        );

        $form['input'][] = array(
            'tab' => 'general',
            'type' => 'free',
            'label' => $this->l('When the customer has successfully selected the delivery method (Validation)'),
            'name' => 'VALIDATION_URL',
        );
        $form['input'][] = array(
            'tab' => 'general',
            'type' => 'free',
            'label' => $this->l('When the client could not select the delivery method (Failed)'),
            'name' => 'RETURN_URL',
        );

        //======================================================================
        // SYSTEM TAB
        $form['tabs']['system'] = $this->l('Colissimo simplicity system parameters');

        $form['input'][] = array(
            'tab' => 'system',
            'col' => 3,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Url of back office Colissimo.'),
            'name' => 'COLISSIMO_URL',
            'desc' => $this->l('Url of back office Colissimo.')
        );
        $form['input'][] = array(
            'tab' => 'system',
            'col' => 3,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Url So Mobile'),
            'name' => 'COLISSIMO_URL_MOBILE',
            'desc' => $this->l('Url of back office Colissimo Mobile. Customers with smartphones or ipad will be redirect there. Warning, this url do not allow delivery in belgium ')
        );
        $form['input'][] = array(
            'tab' => 'system',
            'type' => 'switch',
            'label' => $this->l('Supervision'),
            'name' => 'COLISSIMO_SUP',
            'is_bool' => true,
            'required' => true,
            'desc' => $this->l('Enable or disable the check availability  of Colissimo service.'),
            'values' => array(
                array(
                    'id' => 'active_on',
                    'value' => true,
                    'label' => $this->l('Enabled')
                ),
                array(
                    'id' => 'active_off',
                    'value' => false,
                    'label' => $this->l('Disabled')
                )
            ),
        );
        $form['input'][] = array(
            'tab' => 'system',
            'col' => 3,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Url Supervision'),
            'name' => 'COLISSIMO_SUP_URL',
            'desc' => $this->l('The monitor URL is to ensure the availability of the socolissimo service. We strongly recommend that you do not disable it')
        );

        //======================================================================
        // PRESTASHOP TAB
        $form['tabs']['prestashop'] = $this->l('Colissimo simplicity prestashop parameters');

        $form['input'][] = array(
            'tab' => 'prestashop',
            'col' => 3,
            'type' => 'radio',
            'label' => $this->l('Display Mode'),
            'name' => 'DISPLAY_TYPE',
            'required' => false,
            'desc' => $this->l('Choose your display mode for Simplicity formula'),
            'values' => array(
                array
                    (
                    'id' => 'classic_on',
                    'value' => 0,
                    'label' => $this->l('Classic')
                ),
                array(
                    'id' => 'fancybox_on',
                    'value' => 1,
                    'label' => $this->l('Fancybox')
                ),
                array(
                    'id' => 'iframe_on',
                    'value' => 2,
                    'label' => $this->l('Iframe')
                )
            )
        );
        $form['input'][] = array(
            'tab' => 'prestashop',
            'type' => 'select',
            'required' => true,
            'label' => $this->l('Home carrier'),
            'name' => 'COLISSIMO_CARRIER_ID',
            'options' => array(
                'query' => Carrier::getCarriers($this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS),
                'id' => 'id_carrier',
                'name' => 'name'
            ),
            'desc' => $this->l('Carrier used to get "Colissimo at home" cost')
        );
        $form['input'][] = array(
            'tab' => 'prestashop',
            'type' => 'switch',
            'col' => 4,
            'label' => $this->l('Withdrawal point cost'),
            'name' => 'COLISSIMO_COST_SELLER',
            'is_bool' => true,
            'required' => true,
            'desc' => $this->l('This cost override the normal cost for seller delivery.'),
            'values' => array(
                array(
                    'id' => 'active_on',
                    'value' => true,
                    'label' => $this->l('Enabled')
                ),
                array(
                    'id' => 'active_off',
                    'value' => false,
                    'label' => $this->l('Disabled')
                )
            ),
        );
        $form['input'][] = array(
            'tab' => 'prestashop',
            'col' => 4,
            'type' => 'text',
            'required' => true,
            'label' => $this->l('Withdrawal cost'),
            'name' => 'COLISSIMO_SELLER_AMOUNT',
            'desc' => $this->l('Withdrawal cost for "Colissimo at a withdrawal point"')
        );
        return $form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $return = array();
        foreach ($this->config_single_values_keys as $key) $return[$key] = Configuration::get($key);

        if (!Configuration::get('COLISSIMO_USE_FANCYBOX') && !Configuration::get('COLISSIMO_USE_IFRAME'))
            $display_type = 0;
        elseif (Configuration::get('COLISSIMO_USE_FANCYBOX'))
            $display_type = 1;
        elseif (Configuration::get('COLISSIMO_USE_IFRAME'))
            $display_type = 2;
        $return['DISPLAY_TYPE'] = $display_type;

        $return['VALIDATION_URL'] = '<p class="form-control-static">'.htmlentities($this->url, ENT_NOQUOTES, 'UTF-8').'</p>';
        $return['RETURN_URL'] = '<p class="form-control-static">'.htmlentities($this->url, ENT_NOQUOTES, 'UTF-8').'</p>';
        return $return;
    }

    protected function savePreactivationRequest()
    {
        $employee = new Employee((int)Context::getContext()->cookie->id_employee);

        $data = array(
            'iso_lang' => Tools::strtolower($this->context->language->iso_code),
            'iso_country' => Tools::strtoupper($this->context->country->iso_code),
            'host' => $_SERVER['HTTP_HOST'],
            'ps_version' => _PS_VERSION_,
            'ps_creation' => _PS_CREATION_DATE_,
            'partner' => $this->name,
            'firstname' => $employee->firstname,
            'lastname' => $employee->lastname,
            'email' => $employee->email,
            'shop' => Configuration::get('PS_SHOP_NAME'),
            'type' => 'home',
            'phone' => Configuration::get('COLISSIMO_PERSONAL_PHONE'),
            'zipcode' => Configuration::get('COLISSIMO_PERSONAL_ZIP_CODE'),
            'fields' => serialize(
                array(
                    'quantities' => Configuration::get('COLISSIMO_PERSONAL_QUANTITIES'),
                    'siret' => Configuration::get('COLISSIMO_PERSONAL_SIRET'),
                )
            ),
        );

        $query = http_build_query($data);
        return @Tools::file_get_contents('http://api.prestashop.com/partner/premium/set_request.php?'.$query);
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $is_first = false;
        if (!Configuration::get('COLISSIMO_PERSONAL_DATA')) {
            if (!(bool)preg_match('#^(([\d]{2})([\s]){0,1}){5}$#', Tools::getValue('COLISSIMO_PERSONAL_PHONE'))) {
                $this->context->controller->errors[] = $this->l('Phone number is incorrect');
            }
            if (!(bool)preg_match('#^(([0-8][0-9])|(9[0-5]))[0-9]{3}$#', Tools::getValue('COLISSIMO_PERSONAL_ZIP_CODE'))) {
                $this->context->controller->errors[] = $this->l('Zip code is incorrect');
            }
            if (!Tools::getValue('COLISSIMO_PERSONAL_QUANTITIES')) {
                $this->context->controller->errors[] = $this->l('Mean number is incorrect');
            }
            if (!$this->isSiret(Tools::getValue('COLISSIMO_PERSONAL_SIRET'))) {
                $this->context->controller->errors[] = $this->l('Siret is incorrect');
            }
        }
        if (!Configuration::get('COLISSIMO_PERSONAL_DATA') && !count($this->context->controller->errors)) {
            Configuration::updateValue('COLISSIMO_PERSONAL_PHONE', Tools::getValue('COLISSIMO_PERSONAL_PHONE'));
            Configuration::updateValue('COLISSIMO_PERSONAL_ZIP_CODE', Tools::getValue('COLISSIMO_PERSONAL_ZIP_CODE'));
            Configuration::updateValue('COLISSIMO_PERSONAL_QUANTITIES', Tools::getValue('COLISSIMO_PERSONAL_QUANTITIES'));
            Configuration::updateValue('COLISSIMO_PERSONAL_SIRET', Tools::getValue('COLISSIMO_PERSONAL_SIRET'));
            if ($this->savePreactivationRequest()) {
                $is_first = true;
                Configuration::updateValue('COLISSIMO_PERSONAL_DATA', 1);
            }
        }
        if (!$is_first && Configuration::get('COLISSIMO_PERSONAL_DATA')) {
            if (Tools::getValue('COLISSIMO_ID') == null)
                $this->context->controller->errors[] = $this->l('ID SO not specified');

            if (Tools::getValue('COLISSIMO_KEY') == null)
                $this->context->controller->errors[] = $this->l('Key SO not specified');

            if (Tools::getValue('COLISSIMO_PREPARATION_TIME') == null)
                $this->context->controller->errors[] = $this->l('Preparation time not specified');
            elseif (!Validate::isInt(Tools::getValue('COLISSIMO_PREPARATION_TIME')))
                $this->context->controller->errors[] = $this->l('Invalid preparation time');

            if (Tools::getValue('COLISSIMO_URL') == null)
                $this->context->controller->errors[] = $this->l('Front URL is not specified');
            if (Tools::getValue('COLISSIMO_URL_MOBILE') == null)
                $this->context->controller->errors[] = $this->l('Front mobile URL is not specified');
            if (Tools::getValue('COLISSIMO_SUP_URL') == null)
                $this->context->controller->errors[] = $this->l('Supervision URL is not specified');
        }
        if (!count($this->context->controller->errors) && Configuration::get('COLISSIMO_PERSONAL_DATA') && !$is_first) {
            // re allocation id socolissimo if needed
            if ((int)Tools::getValue('COLISSIMO_CARRIER_ID') != (int)Configuration::get('COLISSIMO_CARRIER_ID')) {
                Configuration::updateValue(
                    'COLISSIMO_CARRIER_ID_HIST', Configuration::get(
                        'COLISSIMO_CARRIER_ID_HIST').'|'.(int)Tools::getValue('COLISSIMO_CARRIER_ID'));
                Configuration::updateValue(
                    'COLISSIMO_CARRIER_ID', (int)Tools::getValue('COLISSIMO_CARRIER_ID'));
                $this->reallocationCarrier((int)Configuration::get('COLISSIMO_CARRIER_ID'));
            }
            foreach ($this->config_single_values_keys as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
            if (Tools::getValue('DISPLAY_TYPE') == 1) {
                Configuration::updateValue('COLISSIMO_USE_FANCYBOX', true);
                Configuration::updateValue('COLISSIMO_USE_IFRAME', false);
            }
            if (Tools::getValue('DISPLAY_TYPE') == 2) {
                Configuration::updateValue('COLISSIMO_USE_IFRAME', true);
                Configuration::updateValue('COLISSIMO_USE_FANCYBOX', false);
            }
            if (Tools::getValue('DISPLAY_TYPE') == 0) {
                Configuration::updateValue('COLISSIMO_USE_IFRAME', false);
                Configuration::updateValue('COLISSIMO_USE_FANCYBOX', false);
            }
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        if (Context::getContext()->customer->logged == true) {
            $id_address_delivery = Context::getContext()->cart->id_address_delivery;
            $address = new Address($id_address_delivery);

            /**
             * Send the details through the API
             * Return the price sent by the API
             */
            return 10;
        }

        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return true;
    }

    protected function addCarrier()
    {
        $carrier = new Carrier();
        $carrier->name = $this->config['name'];
        $carrier->id_tax_rules_group = $this->config['id_tax_rules_group'];
        $carrier->id_zone = $this->config['id_zone'];
        $carrier->url = $this->config['url'];
        $carrier->active = $this->config['active'];
        $carrier->deleted = $this->config['deleted'];
        $carrier->shipping_handling = $this->config['shipping_handling'];
        $carrier->range_behavior = $this->config['range_behavior'];
        $carrier->is_module = $this->config['is_module'];
        $carrier->shipping_external = $this->config['shipping_external'];
        $carrier->external_module_name = $this->config['external_module_name'];
        $carrier->need_range = $this->config['need_range'];

        foreach (Language::getLanguages() as $lang) {
            if ($lang['iso_code'] == 'fr')
                $carrier->delay[$lang['id_lang']] = $this->config['delay'][$lang['iso_code']];
            if ($lang['iso_code'] == 'en')
                $carrier->delay[$lang['id_lang']] = $this->config['delay'][$lang['iso_code']];
        }
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if ($carrier->add() == true) {
            @copy(dirname(__FILE__).'/views/img/colissimo.jpg', _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg');
            Configuration::updateValue('COLISSIMO_CARRIER_ID', (int)$carrier->id);
            return $carrier;
        }

        return false;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group) {
            $groups_ids[] = $group['id_group'];
        }

        $carrier->setGroups($groups_ids);
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '10000';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone) {
            $carrier->addZone($zone['id_zone']);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookActionCarrierUpdate($params)
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if ((int)$params['id_carrier'] == (int)Configuration::get('COLISSIMO_CARRIER_ID')) {
            Configuration::updateValue('COLISSIMO_CARRIER_ID', (int)$params['carrier']->id);
            Configuration::updateValue(
                'COLISSIMO_CARRIER_ID_HIST', Configuration::get('COLISSIMO_CARRIER_ID_HIST').'|'.(int)$params['carrier']->id);
        }
        if ((int)$params['id_carrier'] == (int)Configuration::get('COLISSIMO_CARRIER_ID_SELLER')) {
            Configuration::updateValue('COLISSIMO_CARRIER_ID_SELLER', (int)$params['carrier']->id);
            Configuration::updateValue(
                'COLISSIMO_CARRIER_ID_HIST', Configuration::get('COLISSIMO_CARRIER_ID_HIST').'|'.(int)$params['carrier']->id);
        }
    }

    public function hookDisplayCarrierList($params)
    {
        $carrier_so = new Carrier((int)Configuration::get('COLISSIMO_CARRIER_ID'));

        if (!isset($carrier_so) || !$carrier_so->active)
            return '';
        $id_carrier = (int)$carrier_so->id;
        $country = new Country((int)$params['address']->id_country);
        $carriers = Carrier::getCarriers(
                $this->context->language->id, true, false, false, null, (defined('ALL_CARRIERS') ? ALL_CARRIERS : Carrier::ALL_CARRIERS));

        // bug fix for cart rule with restriction
        CartRule::autoAddToCart($this->context);

        // For now works only with single shipping !
        if (method_exists($params['cart'], 'carrierIsSelected'))
            if ($params['cart']->carrierIsSelected((int)$carrier_so->id, $params['address']->id))
                $id_carrier = (int)$carrier_so->id;

        $customer = new Customer($params['address']->id_customer);

        $gender = array(
            '1' => 'MR',
            '2' => 'MME',
            '3' => 'MLE');

        if (in_array((int)$customer->id_gender, array(
                1,
                2)))
            $cecivility = $gender[(int)$customer->id_gender];
        else
            $cecivility = 'MR';

        $tax_rate = Tax::getCarrierTaxRate($id_carrier, isset($params['cart']->id_address_delivery) ? $params['cart']->id_address_delivery : null);
        $std_cost_with_taxes = number_format((float)$this->getOrderShippingCost * (1 + ($tax_rate / 100)), 2, ',', ' ');
        $seller_cost_with_taxes = 0;
        if (Configuration::get('COLISSIMO_COST_SELLER')) {
            $seller_cost_with_taxes = number_format((float)Configuration::get('COLISSIMO_SELLER_AMOUNT'), 2, ',', ' ');
        }

        /* if ($tax_rate)
          $std_cost_with_taxes = number_format((float)$this->initial_cost * (1 + ($tax_rate / 100)), 2, ',', ' ');
          else
          $std_cost_with_taxes = number_format((float)$this->initial_cost, 2, ',', ' ');
          $seller_cost_with_taxes = 0; */
        /* if ($this->seller_cost)
          if ($tax_rate_seller)
          $seller_cost_with_taxes = number_format((float)$this->seller_cost * (1 + ($tax_rate_seller / 100)), 2, ',', ' ');
          else
          $seller_cost_with_taxes = number_format((float)$this->seller_cost, 2, ',', ' '); */

        $free_shipping = false;

        $rules = $params['cart']->getCartRules();
        if (!empty($rules)) {
            foreach ($rules as $rule) {
                if ($rule['free_shipping'] && !$rule['carrier_restriction']) {
                    $free_shipping = true;
                    break;
                }
            }
            if (!$free_shipping) {
                $key_search = $id_carrier.',';
                $deliveries_list = $params['cart']->getDeliveryOptionList();
                foreach ($deliveries_list as $deliveries) foreach ($deliveries as $key => $elt) if ($key == $key_search)
                            $free_shipping = $elt['is_free'];
            }
        }
        else {
            // for cart rule with restriction
            $key_search = $id_carrier.',';
            $deliveries_list = $params['cart']->getDeliveryOptionList();
            foreach ($deliveries_list as $deliveries) foreach ($deliveries as $key => $elt) if ($key == $key_search)
                        $free_shipping = $elt['is_free'];
        }

        if ($free_shipping) {
            $std_cost_with_taxes = 0;
            $seller_cost_with_taxes = 0;
        }

        // Keep this fields order (see doc.)
        $inputs = array(
            'pudoFOId' => Configuration::get('COLISSIMO_ID'),
            'ceName' => $this->replaceAccentedChars(Tools::substr($params['address']->lastname, 0, 34)),
            'dyPreparationTime' => (int)Configuration::Get('COLISSIMO_PREPARATION_TIME'),
            'dyForwardingCharges' => $std_cost_with_taxes,
            'dyForwardingChargesCMT' => $seller_cost_with_taxes,
            'trClientNumber' => (int)$params['address']->id_customer,
            'orderId' => $this->formatOrderId((int)$params['address']->id),
            'numVersion' => $this->getNumVersion(),
            'ceCivility' => $cecivility,
            'ceFirstName' => $this->replaceAccentedChars(Tools::substr($params['address']->firstname, 0, 29)),
            'ceCompanyName' => $this->replaceAccentedChars(Tools::substr($params['address']->company, 0, 38)),
            'ceAdress3' => $this->replaceAccentedChars(Tools::substr($params['address']->address1, 0, 38)),
            'ceAdress4' => $this->replaceAccentedChars(Tools::substr($params['address']->address2, 0, 38)),
            'ceZipCode' => $this->replaceAccentedChars($params['address']->postcode),
            'ceTown' => $this->replaceAccentedChars(Tools::substr($params['address']->city, 0, 32)),
            'ceEmail' => $this->replaceAccentedChars($params['cookie']->email),
            'cePhoneNumber' => $this->replaceAccentedChars(
                str_replace(array(
                ' ',
                '.',
                '-',
                ',',
                ';',
                '/',
                '\\',
                '(',
                ')'), '', $params['address']->phone_mobile)),
            'dyWeight' => (float)$params['cart']->getTotalWeight() * 1000,
            'trParamPlus' => $carrier_so->id,
            'trReturnUrlKo' => htmlentities($this->url, ENT_NOQUOTES, 'UTF-8'),
            'trReturnUrlOk' => htmlentities($this->url, ENT_NOQUOTES, 'UTF-8'),
            'CHARSET' => 'UTF-8',
            'cePays' => $country->iso_code,
            'trInter' => 1,
            'ceLang' => 'FR'
        );
        if (!$inputs['dyForwardingChargesCMT'] && !Configuration::get('SOCOLISSIMO_COST_SELLER'))
            unset($inputs['dyForwardingChargesCMT']);

        // set params for Api 3.0 if needed
        $inputs = $this->setInputParams($inputs);

        // generate key for API
        $inputs['signature'] = $this->generateKey($inputs);

        // calculate lowest cost
        $from_cost = $std_cost_with_taxes;
        if ($seller_cost_with_taxes && Configuration::get('COLISSIMO_COST_SELLER'))
            if ((float)str_replace(',', '.', $seller_cost_with_taxes) < (float)str_replace(',', '.', $std_cost_with_taxes))
                $from_cost = $seller_cost_with_taxes;
        $rewrite_active = true;
        if (!Configuration::get('PS_REWRITING_SETTINGS'))
            $rewrite_active = false;

        // module link for prestashop 1.5 to get proper url
        /**/$link = new Link();
        $module_link = $link->getModuleLink('socolissimo', 'redirect', array(), true);
        $module_link_mobile = $link->getModuleLink('socolissimo', 'redirectmobile', array(), true);

        // automatic settings api protocol for ssl
        $protocol = 'http://';
        if (Configuration::get('PS_SSL_ENABLED'))
            $protocol = 'https://';

        $this->context->smarty->assign(array(
            'select_label' => $this->l('Select delivery mode'),
            'edit_label' => $this->l('Edit delivery mode'),
            'token' => sha1('colissimo'._COOKIE_KEY_.Context::getContext()->cookie->id_cart),
            'urlSo' => $protocol.Configuration::get('COLISSIMO_URL').'?trReturnUrlKo='.htmlentities($this->url, ENT_NOQUOTES, 'UTF-8'),
            'urlSoMobile' => $protocol.Configuration::get('COLISSIMO_URL_MOBILE').'?trReturnUrlKo='.htmlentities($this->url, ENT_NOQUOTES, 'UTF-8'),
            'id_carrier' => $id_carrier,
            'inputs' => $inputs,
            'initialCost_label' => $this->l('From'),
            'initialCost' => $from_cost.$this->l(' €'), // to change label for price in tpl
            'taxMention' => $this->l(' TTC'), // to change label for price in tpl
            'finishProcess' => $this->l('To choose SoColissimo, click on a delivery method'),
            'rewrite_active' => $rewrite_active,
            'link_socolissimo' => $module_link,
            'link_socolissimo_mobile' => $module_link_mobile
        ));

        $ids = array();
        foreach ($carriers as $carrier) $ids[] = $carrier['id_carrier'];

        if ($params['cart']->id_carrier == Configuration::Get(
                'COLISSIMO_CARRIER_ID') && $this->getDeliveryInfos($this->context->cart->id, $this->context->customer->id))
            $this->context->smarty->assign('already_select_delivery', true);
        else
            $this->context->smarty->assign('already_select_delivery', false);


        // if mobile or iPad
        if (Context::getContext()->getMobileDevice() || $this->isIpad() || $this->isMobile()) {
            return $this->fetchTemplate('socolissimo_redirect_mobile.tpl');
        }
        // route display mode
        if (Configuration::get('PS_ORDER_PROCESS_TYPE') || Configuration::get('COLISSIMO_USE_FANCYBOX'))
            return $this->fetchTemplate('socolissimo_fancybox.tpl');
        if (Configuration::get('COLISSIMO_USE_IFRAME'))
            return $this->fetchTemplate('socolissimo_iframe.tpl');
        return $this->fetchTemplate('socolissimo_redirect.tpl');

        /* else {
          $tab_id_soco = explode('|', Configuration::get('SOCOLISSIMO_CARRIER_ID_HIST'));
          $tab_id_soco[] = $id_carrier;
          $this->context->smarty->assign('ids', $tab_id_soco);
          return $this->fetchTemplate('socolissimo_error.tpl');
          } */
    }

    /**
     * Validate SIRET Code Taken from prestashop core for compatibility 1.4 reason
     * @static
     * @param $siret SIRET Code
     * @return boolean Return true if is valid
     */
    public function isSiret($siret)
    {
        if (Tools::strlen($siret) != 14)
            return false;
        $sum = 0;
        for ($i = 0; $i != 14; $i++) {
            $tmp = ((($i + 1) % 2) + 1) * intval($siret[$i]);
            if ($tmp >= 10)
                $tmp -= 9;
            $sum += $tmp;
        }
        return ($sum % 10 === 0);
    }

    public function getApiPrice($id_cart)
    {
        if ((int)$id_cart) {
            return Db::getInstance()->getValue('SELECT dyforwardingcharges
            FROM '._DB_PREFIX_.'socolissimo_delivery_info
            WHERE id_cart = '.(int)$id_cart);
        }
        return false;
    }

    public function checkZone($id_carrier)
    {
        return (bool)Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'carrier_zone WHERE id_carrier = '.(int)$id_carrier);
    }

    public function checkGroup($id_carrier)
    {
        return (bool)Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'carrier_group WHERE id_carrier = '.(int)$id_carrier);
    }

    public function checkRange($id_carrier)
    {
        $sql = '';
        $carrier = new Carrier($id_carrier);
        if ($carrier->shipping_method) {
            switch ($carrier->shipping_method) {
                case '2' :
                    $sql = 'SELECT * FROM '._DB_PREFIX_.'range_price WHERE id_carrier = '.(int)$id_carrier;
                    break;
                case '1' :
                    $sql = 'SELECT * FROM '._DB_PREFIX_.'range_weight WHERE id_carrier = '.(int)$id_carrier;
                    break;
            }
        }
        if (!$sql) {
            switch (Configuration::get('PS_SHIPPING_METHOD')) {
                case '0' :
                    $sql = 'SELECT * FROM '._DB_PREFIX_.'range_price WHERE id_carrier = '.(int)$id_carrier;
                    break;
                case '1' :
                    $sql = 'SELECT * FROM '._DB_PREFIX_.'range_weight WHERE id_carrier = '.(int)$id_carrier;
                    break;
            }
        }
        return (bool)Db::getInstance()->getRow($sql);
    }

    public function checkDelivery($id_carrier)
    {
        return (bool)Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'delivery WHERE id_carrier = '.(int)$id_carrier);
    }

    public function reallocationCarrier($id_socolissimo)
    {
        // carrier must be module carrier
        Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'carrier SET
            shipping_handling = 0,
            is_module = 1,
            shipping_external = 1,
            need_range = 1,
            external_module_name = "socolissimo"
            WHERE  id_carrier = '.(int)$id_socolissimo);

        // old carrier no longer linked with socolissimo
        Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'carrier SET
            is_module = 0,
            external_module_name = ""
            WHERE  id_carrier NOT IN ( '.(int)Configuration::get('SOCOLISSIMO_CARRIER_ID_SELLER').','.(int)Configuration::get('SOCOLISSIMO_CARRIER_ID').')');
    }

    /**
     * @param $str
     * @return mixed
     */
    public function replaceAccentedChars($str)
    {
        $str = preg_replace(
            array(
            /* Lowercase */
            '/[\x{0105}\x{00E0}\x{00E1}\x{00E2}\x{00E3}\x{00E4}\x{00E5}]/u',
            '/[\x{00E7}\x{010D}\x{0107}]/u',
            '/[\x{010F}]/u',
            '/[\x{00E8}\x{00E9}\x{00EA}\x{00EB}\x{011B}\x{0119}]/u',
            '/[\x{00EC}\x{00ED}\x{00EE}\x{00EF}]/u',
            '/[\x{0142}\x{013E}\x{013A}]/u',
            '/[\x{00F1}\x{0148}]/u',
            '/[\x{00F2}\x{00F3}\x{00F4}\x{00F5}\x{00F6}\x{00F8}]/u',
            '/[\x{0159}\x{0155}]/u',
            '/[\x{015B}\x{0161}]/u',
            '/[\x{00DF}]/u',
            '/[\x{0165}]/u',
            '/[\x{00F9}\x{00FA}\x{00FB}\x{00FC}\x{016F}]/u',
            '/[\x{00FD}\x{00FF}]/u',
            '/[\x{017C}\x{017A}\x{017E}]/u',
            '/[\x{00E6}]/u',
            '/[\x{0153}]/u',
            /* Uppercase */
            '/[\x{0104}\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u',
            '/[\x{00C7}\x{010C}\x{0106}]/u',
            '/[\x{010E}]/u',
            '/[\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{011A}\x{0118}]/u',
            '/[\x{0141}\x{013D}\x{0139}]/u',
            '/[\x{00D1}\x{0147}]/u',
            '/[\x{00D3}]/u',
            '/[\x{0158}\x{0154}]/u',
            '/[\x{015A}\x{0160}]/u',
            '/[\x{0164}]/u',
            '/[\x{00D9}\x{00DA}\x{00DB}\x{00DC}\x{016E}]/u',
            '/[\x{017B}\x{0179}\x{017D}]/u',
            '/[\x{00C6}]/u',
            '/[\x{0152}]/u',
            ),
            array(
            'a',
            'c',
            'd',
            'e',
            'i',
            'l',
            'n',
            'o',
            'r',
            's',
            'ss',
            't',
            'u',
            'y',
            'z',
            'ae',
            'oe',
            'A',
            'C',
            'D',
            'E',
            'L',
            'N',
            'O',
            'R',
            'S',
            'T',
            'U',
            'Z',
            'AE',
            'OE'
            ), $str);
        $array_unauthorised_api = array(
            ';',
            '€',
            '~',
            '#',
            '{',
            '(',
            '[',
            '|',
            '\\',
            '^',
            ')',
            ']',
            '=',
            '}',
            '$',
            '¤',
            '£',
            '%',
            'μ',
            '*',
            '§',
            '!',
            '°',
            '²',
            '"');
        foreach ($array_unauthorised_api as $key => $value) $str = str_replace($value, '', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return $str;
    }

    /**
     * @param array
     * @return array
     */
    public function setInputParams($inputs)
    {
        $get_mobile_device = Context::getContext()->getMobileDevice();

        // set api params for 4.0 and mobile
        if ($get_mobile_device || $this->isIpad() || $this->isMobile()) {
            unset($inputs['CHARSET']);
            $inputs['numVersion'] = '4.0';
        }
        return $inputs;
    }

    /**
     * Check if agent user is iPad(for so_mobile)
     * @return bool
     */
    public function isIpad()
    {
        return (bool)strpos($_SERVER['HTTP_USER_AGENT'], 'iPad');
    }

    public function isMobile()
    {
        if (method_exists(Context::getContext()->mobile_detect, 'isMobile'))
            return (bool)Context::getContext()->mobile_detect->isMobile();
        else
            return false;
    }

    /**
     * Generate the signed key
     *
     * @static
     * @param $params
     * @return string
     */
    public function generateKey($params)
    {
        $str = '';

        foreach ($params as $key => $value) if (!in_array(Tools::strtoupper($key), array(
                    'SIGNATURE')))
                $str .= utf8_decode($value);

        return sha1($str.Tools::strtolower(Configuration::get('COLISSIMO_KEY')));
    }
}