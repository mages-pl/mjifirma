<?php
/**
 * Module Mjifirma
 * @author MAGES Michał Jendraszczyk
 * @copyright (c) 2020, MAGES Michał Jendraszczyk
 * @license http://mages.pl MAGES Michał Jendraszczyk
 */

set_time_limit(0);

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mjifirma extends Module
{
    public $prefix;
    public $platnosci;
    public $sposob_zaplaty;
    public $termin_platnosci;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->name = 'mjifirma';
        $this->tab = 'billing_invoicing';
        $this->author = 'MAGES Michał Jendraszczyk';
        $this->version = '1.0.0';
        $this->module_key = '8a6cef9c9d0543924539d90136de28a1';

        $this->prefix = $this->name . "_";
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Integration with iFirima');
        $this->description = $this->l('Module which can send invoice from your store to ifirma.pl');

        $this->confirmUninstall = $this->l('Remove module?');

        $this->platnosci = array(
            array(
               "id_payment" => "GTK",
               "name" => "GTK – gotówka",
                ),
            array(
               "id_payment" => "POB",
               "name" => "POB – za pobraniem",
                ),
            array(
               "id_payment" => "PRZ",
               "name" => "PRZ – przelew",
                ),
            array(
               "id_payment" => "KAR",
               "name" => "KAR – karta",
                ),
            array(
               "id_payment" => "PZA",
               "name" => "PZA – polecenie zapłaty",
                ),
            array(
               "id_payment" => "CZK",
               "name" => "CZK – czek",
                ),
            array(
               "id_payment" => "KOM",
               "name" => "KOM – kompensata",
                ),
            array(
               "id_payment" => "BAR",
               "name" => "BAR – barter",
                ),
            array(
               "id_payment" => "DOT",
               "name" => "DOT – DotPay",
                ),
            array(
               "id_payment" => "PAL",
               "name" => "PAL – PayPal",
                ),
            array(
               "id_payment" => "ALG",
               "name" => "ALG – PayU",
                ),
            array(
               "id_payment" => "P24",
               "name" => "P24 – Przelewy24",
                ),
            array(
               "id_payment" => "TPA",
               "name" => "TPA – tpay.com",
                ),
            array(
               "id_payment" => "ELE",
               "name" => "ELE – płatność elektroniczna",
                )
        );
        $this->termin_platnosci = date('Y-m-d', strtotime("+7 day"));
        if (Tools::getValue('id_order')) {
            $order = new Order(Tools::getValue('id_order'));
            $this->sposob_zaplaty = (Mjifirma::getPaymentid($order->module) != '') ? Configuration::get($this->prefix . 'payment_option_'.Mjifirma::getPaymentid($order->module)) : "PRZ";
        }
        $this->api_token = trim(Configuration::get($this->prefix . 'mjifirma_klucz'));
    }

    /**
     * Instalacja zakładki
     * @param type $tabClass
     * @param type $tabName
     * @param type $idTabParent
     * @return boolean
     */
    private function installModuleTab($tabClass, $tabName, $idTabParent)
    {
        $tab = new Tab();
        $tab->name = $tabName;
        $tab->class_name = $tabClass;
        $tab->module = $this->name;
        $tab->id_parent = $idTabParent;
        $tab->position = 98;
        if (!$tab->save()) {
            return false;
        }
        return true;
    }

    /**
     * Odinstalowanie zakładki
     * @param type $tabClass
     */
    public function uninstallModuleTab($tabClass)
    {
        $idTab = Tab::getIdFromClassName($tabClass);
        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }
    }
    
    /**
     * Instalacja
     * @return type
     */
    public function install()
    {
        Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mjifirma_invoice` (
        `id_mjifirma` INT(11) UNSIGNED NOT null auto_increment,
        `id_order` INT(11) UNSIGNED NOT null,
        `id_fv` INT(11),
        `id_pf` INT(11),
        PRIMARY KEY (`id_mjifirma`),
        KEY `id_order` (`id_order`)) DEFAULT CHARSET=utf8;');

        return parent::install() && $this->registerHook('actionOrderStatusPostUpdate') && $this->registerHook('displayAdminOrder') && $this->registerHook('actionValidateOrder') && $this->installModuleTab('AdminMjifirmainvoice', array(Configuration::get('PS_LANG_DEFAULT') => 'Integracja iFirma'), Tab::getIdFromClassName('AdminParentOrders'));
    }

    /**
     * Tworzenie proforma po otrzymaniu zamówienia
     * @param type $params
     */
    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        //$this->sendPv($order); removed because currently this method is unavailable
    }

    /**
     * Aktualizuj fakture
     * @param type $id_order
     * @param type $id_fv
     */
    public function aktualizujFakture($id_order, $id_fv)
    {
        Db::getInstance()->Execute('UPDATE `' . _DB_PREFIX_ . 'mjifirma_invoice` SET `id_fv` = "' . pSQL($id_fv) . '" WHERE id_order = "' . pSQL($id_order) . '"');
    }

    /**
     * Dodaj fakurę
     * @param type $id_order
     * @param type $id_pf
     * @param type $typ
     */
    public function dodajFakture($id_order, $id_pf, $id_fv, $typ)
    {
        if ($typ == 'pf') {
            Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'mjifirma_invoice` (`id_order`, `id_fv`,`id_pf`) VALUES ("' . pSQL((int) $id_order) . '", "","' . pSQL($id_pf) . '")');
        } else {
            Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'mjifirma_invoice` (`id_order`, `id_fv`,`id_pf`) VALUES ("' . pSQL((int) $id_order) . '", "'.pSQL($id_fv).'","")');
        }
    }
    
    /**
     * Wysyłka faktury po ustawieniu okreslonego statusu
     * @param type $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
    }
    /**
     * Wysyłka
     * @param type $order
     * @return type
     */
    public function sendFv($order)
    {
        $adres = new Address($order->id_address_invoice);
        $klient = new Customer($order->id_customer);

        
        if (!empty($adres->vat_number)) {
            $os_fizyczna = false;
        } else {
            $os_fizyczna = true;
        }
        
        $pozycje = '';
        
        $dt = 0;
        $tax_rate = 0;
        foreach ($order->getProducts() as $product) {
            $separator = ',';
            if (((count($order->getProducts())-1) == $dt) && ($order->total_shipping == 0)) {
                $separator = '';
            }
            $tax_rate = ($product['tax_rate']/100);
            $pozycje .= '{'
                    . '"StawkaVat":'.($product['tax_rate']/100).','
                    . '"Ilosc":'.$product['product_quantity'].','
                    . '"CenaJednostkowa":'.number_format($product['product_price']*(1+($product['tax_rate']/100)), 2, '.', '').','
                    . '"NazwaPelna":"'.$product['product_name'].'",'
                    . '"Jednostka":"sztuk",'
                    . '"PKWiU":"",'
                    . '"TypStawkiVat":"PRC"'
                    . '}'.$separator.'';
            $dt++;
        }
        
        /**
         * Wysyłka
         */
        if ($order->total_shipping > 0) {
            $pozycje .= '{'
                    . '"StawkaVat":'.$tax_rate.','
                    . '"Ilosc": 1,'
                    . '"CenaJednostkowa":'.number_format($order->total_shipping, 2, '.', '').','
                    . '"NazwaPelna":"'.$this->l('Shipping').'",'
                    . '"Jednostka":"usł",'
                    . '"PKWiU":"",'
                    . '"TypStawkiVat":"PRC"'
                    . '}';
        }
        //, strtotime($order->date_add)
        
        $requestContent = ''
                . '{"Zaplacono":'.number_format($order->total_paid, 2, '.', '').','
                . '"LiczOd":"BRT",'
                . '"NumerKontaBankowego":null,'
                . '"DataWystawienia":"'.date('Y-m-d').'",'
                . '"MiejsceWystawienia":"'.Configuration::get($this->prefix.'miasto_wystawienia').'",'
                . '"DataSprzedazy":"'.date('Y-m-d').'",'
                . '"FormatDatySprzedazy":"DZN",'
                . '"TerminPlatnosci":"'.$this->termin_platnosci.'",'
                . '"SposobZaplaty":"'.$this->sposob_zaplaty.'",'
                . '"NazwaSeriiNumeracji":"default",'
                . '"NazwaSzablonu":"logo",'
                . '"RodzajPodpisuOdbiorcy":"OUP",'
                . '"PodpisOdbiorcy":"Odbiorca",'
                . '"PodpisWystawcy":"Wystawca",'
                . '"Uwagi":"Zamówienie nr #'.$order->reference.'",'
                . '"WidocznyNumerGios":true,'
                . '"Numer":null,'
                . '"Pozycje":['.$pozycje.'],'
                . '"Kontrahent":{'
                    . '"Nazwa":"'.$adres->firstname.' '.$adres->lastname.'",'
                    . '"Identyfikator":null,'
                    . '"PrefiksUE":null,'
                    . '"NIP":"'.$adres->vat_number.'",'
                    . '"Ulica":"'.$adres->address1.'",'
                    . '"KodPocztowy":"'.$adres->postcode.'",'
                    . '"Kraj":"Polska",'
                    . '"Miejscowosc":"'.$adres->city.'",'
                    . '"Email":"'.$klient->email.'",'
                    . '"Telefon":"'.$adres->phone.'",'
                    . '"OsobaFizyczna":'.$os_fizyczna.''
                    . '}}';

        //{ "response" : { "Kod" : 201, "Informacja" : "Data sprzedaży musi być zgodna z miesiącem i rokiem księgowym" } }
        
       // $requestContent = '{"Zaplacono":78,"LiczOd":"BRT","NumerKontaBankowego":null,"DataWystawienia":"'.date('Y-m-d').'","MiejsceWystawienia":"Miasto","DataSprzedazy":"'.date('Y-m-d').'","FormatDatySprzedazy":"DZN","TerminPlatnosci":null,"SposobZaplaty":"PRZ","NazwaSeriiNumeracji":"default","NazwaSzablonu":"logo","RodzajPodpisuOdbiorcy":"OUP","PodpisOdbiorcy":"Odbiorca","PodpisWystawcy":"Wystawca","Uwagi":"uwagi","WidocznyNumerGios":true,"Numer":null,"Pozycje":[{"StawkaVat":0.23,"Ilosc":1,"CenaJednostkowa":78.00,"NazwaPelna":"cos","Jednostka":"sztuk","PKWiU":"","TypStawkiVat":"PRC"}],"Kontrahent":{"Nazwa":"Imie Nazwisko","Identyfikator":null,"PrefiksUE":null,"NIP":null,"Ulica":"Ulica","KodPocztowy":"11-111","Kraj":"Polska","Miejscowosc":"Miejscowość","Email":"em@il.pl","Telefon":"111111111","OsobaFizyczna":true}}';
        $nazwaUsera = Configuration::get($this->prefix.'login');
        $nazwaKlucza = 'faktura';

        $url = "https://www.ifirma.pl/iapi/fakturakraj.json";

        $api_key = array();
        $part_key = '';
        for ($i=0; $i<Tools::strlen(Configuration::get($this->prefix.'klucz_api_faktura')); $i++) {
            $part_key .= Configuration::get($this->prefix.'klucz_api_faktura')[$i];
            if ((($i%2) == 1) && ($i != 0)) {
                $api_key[] = $part_key;
                $part_key = '';
            }
        }
        
        $klucz = chr(hexdec($api_key[0])).chr(hexdec($api_key[1])).chr(hexdec($api_key[2])).chr(hexdec($api_key[3])).chr(hexdec($api_key[4])).chr(hexdec($api_key[5])).chr(hexdec($api_key[6])).chr(hexdec($api_key[7]));

        $hashWiadomosci = hash_hmac('sha1', $url.$nazwaUsera.$nazwaKlucza.$requestContent, $klucz);
        $headers = array(
            'Accept: application/json',
            'Content-type: application/json; charset=UTF-8',
            'Authentication: IAPIS user='.$nazwaUsera.', hmac-sha1='.$hashWiadomosci
        );
        
        $response = json_decode($this->makeRequest($url, $headers, "POST", $requestContent), true);
        if ($response["response"]['Kod'] == '201') {
            echo $response["response"]['Informacja'];
            exit();
        } else {
            $this->dodajFakture($order->id, null, $response["response"]['Identyfikator'], 'fv');
        
            $link = new Link();
            return $link->getLegacyAdminLink("AdminOrders", true, ['vieworder' => '', 'id_order' => $order->id]);
        }
    }

    /**
     * Pobranie id modułu płatności
     * @param type $payment_name
     * @return boolean
     */
    public static function getPaymentid($payment_name)
    {
        $sql = "SELECT * FROM "._DB_PREFIX_."module WHERE name = '".pSQL($payment_name)."' LIMIT 1";
        if (count(DB::getInstance()->ExecuteS($sql, 1, 0)) > 0) {
            return DB::getInstance()->ExecuteS($sql, 1, 0)[0]['id_module'];
        } else {
            return false;
        }
    }
    /**
     * Wyswietlanie opcji wysyłki faktur z szczegółów zamówienia
     * @return type
     */
    public function hookDisplayAdminOrder()
    {
        $order_id = Tools::getValue('id_order');
        $order = new Order((int) $order_id);

        if (empty(Configuration::get($this->prefix . 'klucz_api_faktura'))
                && empty(Configuration::get($this->prefix . 'klucz_api_rachunek'))
                && empty(Configuration::get($this->prefix . 'klucz_api_abonament'))
                && empty(Configuration::get($this->prefix . 'login'))
                && empty(Configuration::get($this->prefix . 'miasto_wystawienia'))
                && empty(Configuration::get($this->prefix . 'klucz_api_wydatek'))) {
            return $this->display(__file__, '/views/templates/admin/displayerrorifirma.tpl');
        } else {
            $this->context->smarty->assign(array(
                'invoice' => '',
                'pf' => '',
                'single_invoice' => $this->getInvoice($order_id),
                'id_order' => $order_id,
                'default_language' => (int) Configuration::get('PS_LANG_DEFAULT'),
                'order_product' => $order->getCustomer()
            ));
            return $this->display(__file__, '/views/templates/admin/displayAdminOrder.tpl');
        }
    }
    /**
     * Wyświetlenie fv z ifirma
     * @param type $id_order
     * @return type
     */
    public function getInvoiceApi($id_order)
    {
        $nazwaUsera = Configuration::get($this->prefix.'login');
        $nazwaKlucza = 'faktura';
        $requestContent='';
        $url = "https://www.ifirma.pl/iapi/fakturakraj/".$this->getInvoice($id_order)[0]['id_fv'].".pdf.single";

        $api_key = array();
        $part_key = '';
        for ($i=0; $i<Tools::strlen(Configuration::get($this->prefix.'klucz_api_faktura')); $i++) {
            $part_key .= Configuration::get($this->prefix.'klucz_api_faktura')[$i];
            if ((($i%2) == 1) && ($i != 0)) {
                $api_key[] = $part_key;
                $part_key = '';
            }
        }

        $klucz = chr(hexdec($api_key[0])).chr(hexdec($api_key[1])).chr(hexdec($api_key[2])).chr(hexdec($api_key[3])).chr(hexdec($api_key[4])).chr(hexdec($api_key[5])).chr(hexdec($api_key[6])).chr(hexdec($api_key[7]));

        $hashWiadomosci = hash_hmac('sha1', $url.$nazwaUsera.$nazwaKlucza.$requestContent, $klucz);
        $headers = array(
            'Accept: application/pdf',
            'Content-type: application/pdf; charset = UTF-8',
            'Authentication: IAPIS user='.$nazwaUsera.', hmac-sha1='.$hashWiadomosci
         );
        echo $this->makeRequest($url, $headers, "GET", $requestContent);
        exit();
    }

    /**
     * Pobierz Fv
     * @param type $id_order
     * @return type
     */
    public function getInvoice($id_order)
    {
        $sql = "SELECT * FROM "._DB_PREFIX_."mjifirma_invoice WHERE id_order = '".pSQL($id_order)."'";
        return DB::getInstance()->ExecuteS($sql, 1, 0);
    }
    /**
     * Odsintalowywanie
     * @return type
     */
    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallModuleTab('AdminMjifirmainvoice');
    }
    
    /**
     * Procesowanie zapisywania konfigu
     * @return type
     */
    public function postProcess()
    {
        $payments = PaymentModule::getInstalledPaymentModules();
        //Zapisanie danych do konfiguracji połączenia
        if (Tools::isSubmit('saveApi')) {
            if (!empty(Tools::getValue($this->prefix . 'klucz_api_faktura'))
                && !empty(Tools::getValue($this->prefix . 'klucz_api_rachunek'))
                && !empty(Tools::getValue($this->prefix . 'klucz_api_abonament'))
                && !empty(Tools::getValue($this->prefix . 'login'))
                && !empty(Tools::getValue($this->prefix . 'miasto_wystawienia'))
                && !empty(Tools::getValue($this->prefix . 'klucz_api_wydatek'))
                    ) {
                Configuration::updateValue($this->prefix . 'klucz_api_faktura', Tools::getValue($this->prefix . 'klucz_api_faktura'));
                Configuration::updateValue($this->prefix . 'klucz_api_rachunek', Tools::getValue($this->prefix . 'klucz_api_rachunek'));
                Configuration::updateValue($this->prefix . 'klucz_api_abonament', Tools::getValue($this->prefix . 'klucz_api_abonament'));
                Configuration::updateValue($this->prefix . 'klucz_api_wydatek', Tools::getValue($this->prefix . 'klucz_api_wydatek'));
                Configuration::updateValue($this->prefix . 'login', Tools::getValue($this->prefix . 'login'));
                Configuration::updateValue($this->prefix . 'miasto_wystawienia', Tools::getValue($this->prefix . 'miasto_wystawienia'));

                return $this->displayConfirmation($this->l('Saved successfully'));
            } else {
                return $this->displayError($this->l('Fields are required'));
            }
        } if (Tools::isSubmit('checkApi')) {
            if (json_decode($this->testIntegration(), 1)['response']['Kod'] == '0') {
                return $this->displayConfirmation($this->l('Connection successfully'));
            } else {
                return $this->displayError($this->l('Error while API connection'));
            }
        } if (Tools::isSubmit('save_ifirma')) {
            foreach ($payments as $payment) {
                Configuration::updateValue($this->prefix . 'payment_option_'.$payment['id_module'], Tools::getValue($this->prefix . 'payment_option_'.$payment['id_module']));
            }
            return $this->displayConfirmation($this->l('Mapping saved successfully'));
        }
    }

    /**
     * Budowanie formularza
     * @return type
     */
    public function renderForm()
    {
        $fields_form = array();

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('API Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API key invoice'),
                    'name' => $this->prefix . 'klucz_api_faktura',
                    'disabled' => false,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API key account'),
                    'name' => $this->prefix . 'klucz_api_rachunek',
                    'disabled' => false,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API key subscription'),
                    'name' => $this->prefix . 'klucz_api_abonament',
                    'disabled' => false,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API key expense'),
                    'name' => $this->prefix . 'klucz_api_wydatek',
                    'disabled' => false,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('City for send invoice'),
                    'name' => $this->prefix . 'miasto_wystawienia',
                    'disabled' => false,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Login iFirma'),
                    'name' => $this->prefix . 'login',
                    'disabled' => false,
                    'required' => true,
                ),
                
            ),
            'buttons' => array(
                'checkApi' => array(
                    'title' => $this->l('Check connection'),
                    'name' => 'checkApi',
                    'type' => 'submit',
                    'id' => 'checkSync',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-refresh'
                ),
                'saveApi' => array(
                    'title' => $this->l('Save'),
                    'name' => 'saveApi',
                    'type' => 'submit',
                    'id' => 'saveApi',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-save'
                ))
        );

        $payments = PaymentModule::getInstalledPaymentModules();

        $options_payments = array();
        foreach ($payments as $payment) {
            $option =  array(
                    'type' => 'select',
                    'label' => $this->l('Ifirma payment associated with payment module: '.$payment['name']),
                    'name' => $this->prefix . 'payment_option_'.$payment['id_module'],
                    'disabled' => false,
                    'required' => true,
                    'options' => array(
                        'query' => $this->platnosci,
                        'id' => 'id_payment',
                        'name' => 'name',
                    ),
                );
            array_push($options_payments, $option);
        }
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Mapping'),
            ),
            'input' => $options_payments,
            'submit' => array(
                'title' => $this->l('Save'),
                'name' => 'save_ifirma',
                'class' => 'btn btn-default pull-right',
            ),
        );

        $form = new HelperForm();
        $form->token = Tools::getAdminTokenLite('AdminModules');

        
        foreach ($payments as $payment) {
            $form->tpl_vars['fields_value'][$this->prefix . 'payment_option_'.$payment['id_module']] = Tools::getValue($this->prefix . 'payment_option_'.$payment['id_module'], Configuration::get($this->prefix . 'payment_option_'.$payment['id_module']));
        }
        $form->tpl_vars['fields_value'][$this->prefix . 'klucz_api_faktura'] = Tools::getValue($this->prefix . 'klucz_api_faktura', Configuration::get($this->prefix . 'klucz_api_faktura'));
        $form->tpl_vars['fields_value'][$this->prefix . 'klucz_api_rachunek'] = Tools::getValue($this->prefix . 'klucz_api_rachunek', Configuration::get($this->prefix . 'klucz_api_rachunek'));
        $form->tpl_vars['fields_value'][$this->prefix . 'klucz_api_abonament'] = Tools::getValue($this->prefix . 'klucz_api_abonament', Configuration::get($this->prefix . 'klucz_api_abonament'));
        $form->tpl_vars['fields_value'][$this->prefix . 'klucz_api_wydatek'] = Tools::getValue($this->prefix . 'klucz_api_wydatek', Configuration::get($this->prefix . 'klucz_api_wydatek'));
        $form->tpl_vars['fields_value'][$this->prefix . 'login'] = Tools::getValue($this->prefix . 'login', Configuration::get($this->prefix . 'login'));
        $form->tpl_vars['fields_value'][$this->prefix . 'miasto_wystawienia'] = Tools::getValue($this->prefix . 'miasto_wystawienia', Configuration::get($this->prefix . 'miasto_wystawienia'));
        
        
        
        return $form->generateForm($fields_form);
    }
    /**
     * Wyświetlenie contentu
     * @return type
     */
    public function getContent()
    {
        return $this->postProcess() . $this->renderForm();
    }

    /**
     * Test połączenia API
     * @return type
     */
    private function testIntegration()
    {
        $requestContent = '';
        $nazwaUsera = Configuration::get($this->prefix.'login');
        $nazwaKlucza = 'abonent';

        $url = "https://www.ifirma.pl/iapi/abonent/limit.json";

        $api_key = array();
        $part_key = '';
        for ($i=0; $i<Tools::strlen(Configuration::get($this->prefix.'klucz_api_abonament')); $i++) {
            $part_key .= Configuration::get($this->prefix.'klucz_api_abonament')[$i];
            if ((($i%2) == 1) && ($i != 0)) {
                $api_key[] = $part_key;
                $part_key = '';
            }
        }
        
        $klucz = @chr(hexdec($api_key[0])).@chr(hexdec($api_key[1])).@chr(hexdec($api_key[2])).@chr(hexdec($api_key[3])).@chr(hexdec($api_key[4])).@chr(hexdec($api_key[5])).@chr(hexdec($api_key[6])).@chr(hexdec($api_key[7]));

        $hashWiadomosci = hash_hmac('sha1', $url.$nazwaUsera.$nazwaKlucza.$requestContent, $klucz);
        $headers = array(
            'Accept: application/json',
            'Content-type: application/json; charset=UTF-8',
            'Authentication: IAPIS user='.$nazwaUsera.', hmac-sha1='.$hashWiadomosci
        );

        return $this->makeRequest("https://www.ifirma.pl/iapi/abonent/limit.json", $headers, "GET", $requestContent);
    }
    /**
     * Wykonywanie requestów
     * @param type $url
     * @param type $headers
     * @param type $method
     * @param type $requestContent
     * @return type
     */
    public function makeRequest($url, $headers, $method, $requestContent)
    {
        $curlHandle = curl_init($url);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 300);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 100);
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);
        
        curl_setopt($curlHandle, CURLOPT_HTTPGET, false);
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $method);

        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $requestContent);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);

        return curl_exec($curlHandle);
    }
}
