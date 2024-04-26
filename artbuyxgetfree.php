<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class artbuyxgetfree extends Module
{
    public function __construct()
    {
        $this->name = 'artbuyxgetfree';
        $this->tab = 'pricing_promotion';
        $this->version = '0.0.1';
        $this->author = 'Artonomi';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Art Buy X Get Free');
        $this->description = $this->l('Provides a discount on the cheapest item in the cart when minimum unique item and quantity requirements are met.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionCartSummary') &&
            $this->registerHook('actionCartUpdate') &&
            $this->registerHook('displayShoppingCart') &&
            $this->registerHook('header') &&
            Configuration::updateValue('ART_BUY_X', 4) && // Örnek değer
            Configuration::updateValue('ART_DISCOUNT_ACTIVE', false); // Örnek değer
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('ART_BUY_X') &&
            Configuration::deleteByName('ART_DISCOUNT_ACTIVE');
    }

    public function getContent()
    {

        if (Tools::isSubmit('submit' . $this->name)) {
            $buy_x = (int)Tools::getValue('buy_x');
            $discount_active = (bool)Tools::getValue('discount_active');

            Configuration::updateValue('ART_BUY_X', $buy_x);
            Configuration::updateValue('ART_DISCOUNT_ACTIVE', $discount_active);

            $this->context->smarty->assign('confirmation', $this->l('Settings updated'));
        }

        // $token = Tools::getAdminTokenLite('AdminModules');
        // $this->context->smarty->assign([
        //     'token' => $token,
        //     'buy_x' => Configuration::get('ART_BUY_X'),
        //     'discount_active' => Configuration::get('ART_DISCOUNT_ACTIVE')
        // ]);
        // // die('asdasd');

        // return $this->display(__FILE__, 'views/templates/admin/configure.tpl');

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Buy X'),
                        'name' => 'buy_x',
                        'size' => 20,
                        'required' => true
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Discount Active'),
                        'name' => 'discount_active',
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'discount_active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'discount_active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ]
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ]
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $this->context->language->id;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;

        $helper->fields_value['buy_x'] = Configuration::get('ART_BUY_X');
        $helper->fields_value['discount_active'] = Configuration::get('ART_DISCOUNT_ACTIVE');

        return $helper->generateForm([$form]);
    }

    public function hookHeader()
    {
        if (!Configuration::get('ART_DISCOUNT_ACTIVE')) {
            return;  // Eğer indirim modülü pasifse işlem yapma
        }

        $cart = $this->context->cart;

        $cartRules = $cart->getCartRules();
        foreach ($cartRules as $cartRule) {
            $cartRule = new CartRule($cartRule['id_cart_rule']);
            $cartRule->delete();
        }

        $products = $cart->getProducts(true);


        $eligibleProducts = [];
        $totalCount = 0;

        foreach ($products as $product) {
            if ($product['price_wt'] > 0 && $product['quantity'] > 0) {  // Ürün fiyatı 0'dan büyük ve mevcutsa
                $eligibleProducts[] = $product;
                $totalCount += $product['quantity'];
            }
        }


        if ($totalCount < Configuration::get('ART_BUY_X')) {
            // echo 'uygun ürün sayısı yeterli değil';
            return;  // Eğer uygun ürün sayısı yeterli değilse indirim yapma
        }
        $cheapestProduct = $this->findLowestPriceRow($eligibleProducts);


        $discountAmount = round($cheapestProduct['price_wt'], 2);
        $cart->addCartRule($this->createDiscount($discountAmount));

        // add css
        $this->context->controller->addCSS($this->_path . 'views/css/style.css');
    }

    function findLowestPriceRow($array)
    {
        // Başlangıçta minimum değeri belirlemek için ilk elemanı kullanıyoruz.
        $minRow = $array[0];
        foreach ($array as $row) {
            if ($row['price_wt'] < $minRow['price_wt']) {
                $minRow = $row;
            }
        }
        return $minRow;
    }

    public function hookDisplayShoppingCart($params)
    {
        //die('hookDisplayShoppingCart');
    }

    public function hookActionCartUpdate($params)
    {
        die('hookActionCartUpdate');
    }

    public function hookActionCartSummary($params)
    {
        die('asdasd');
        if (!Configuration::get('ART_DISCOUNT_ACTIVE')) {
            return;  // Eğer indirim modülü pasifse işlem yapma
        }

        $cart = $params['cart'];
        $products = $cart->getProducts(true);
        $eligibleProducts = [];

        foreach ($products as $product) {
            if ($product['price_wt'] > 0 && $product['quantity'] > 0) {  // Ürün fiyatı 0'dan büyük ve mevcutsa
                $eligibleProducts[] = $product;
            }
        }

        if (count($eligibleProducts) < Configuration::get('ART_BUY_X')) {
            return;  // Eğer uygun ürün sayısı yeterli değilse indirim yapma
        }

        // Sepetteki en ucuz ürünü bul
        $cheapestProduct = min($eligibleProducts, function ($a, $b) {
            return $a['price_wt'] <=> $b['price_wt'];
        });

        // Sepet toplamından indirim yap
        $discountAmount = $cheapestProduct['price_wt'];
        $cart->addCartRule($this->createDiscount($discountAmount));

        return 'Discount applied to the cheapest product';
    }

    private function createDiscount($discountAmount)
    {
        $cart = $this->context->cart;
        $cartRule = new CartRule();
        $cartRule->name = array(
            1 => '4 Al 3 Öde Kampanyası',
            2 => 'Buy 4 Pay 3 Campaign',
        );
        // $cartRule->id_customer = $cart->id_customer;
        $cartRule->code = 'BUYXGETONEFREE' . rand(1000, 9999);
        $cartRule->value = $discountAmount;
        $cartRule->reduction_amount = $discountAmount;
        $cartRule->active = 1;
        $cartRule->date_from = date('Y-m-d H:i:s');
        $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 month'));
        $cartRule->reduction_currency = $cart->id_currency;
        $cartRule->reduction_tax = 1;
        $cartRule->add();

        return $cartRule->id;
    }
}
