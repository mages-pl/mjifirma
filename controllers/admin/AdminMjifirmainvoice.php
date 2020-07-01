<?php
/**
 * Module Mjifirma
 * @author MAGES Michał Jendraszczyk
 * @copyright (c) 2020, MAGES Michał Jendraszczyk
 * @license http://mages.pl MAGES Michał Jendraszczyk
 */

include_once(dirname(__FILE__).'/../../mjifirma.php');

class AdminMjifirmainvoiceController extends ModuleAdminController
{
    public $id_order;
    public $kind;
    public $invoice;

    public function __construct()
    {
        if (empty(Tools::getValue('id_order'))) {
            Tools::redirectAdmin('index.php?controller=AdminModules&configure=mjifirma&token=' . Tools::getAdminTokenLite('AdminModules'));
        } else {
            $this->id_order = Tools::getValue('id_order');
            $this->kind = Tools::getValue('kind');
            
            $this->bootstrap = true;
            parent::__construct();
            
            if ($this->kind == 'vat') {
                $order = new Order($this->id_order);
                return (new Mjifirma())->sendFv($order);
            }
            
            if ($this->kind == 'pf') {
                $order = new Order($this->id_order);
                return (new Mjifirma())->sendPv($order);
            }
            
        }
    }
}
