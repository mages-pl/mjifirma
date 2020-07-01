{*
 * Module Mjifirma
 * @author MAGES Michał Jendraszczyk
 * @copyright (c) 2020, MAGES Michał Jendraszczyk
 * @license http://mages.pl MAGES Michał Jendraszczyk
*}

<div class="clearfix"></div>
<div class="panel panel-body">
    <img src='../modules/mjifirma/logo.png' style="width:120px;">
        <table class="table">
            <thead>
                <tr>
                    <th><b>{l s='Position' mod='mjifirma'}</b></th>
                    <th><b>{l s='Insert' mod='mjifirma'}</b></th>
                </tr>
            </thead>
            <tr>
                <td><strong>Faktura</strong></td>
                <td>
                    {if count($single_invoice) == 0}
                        <a href="{$link->getAdminLink('AdminMjifirmainvoice', true, [], ['id_order' => $id_order, 'kind' => 'vat'])|escape:'htmlall':'UTF-8'}" class="btn btn-default">Wystaw fakturę VAT</a>
                    {else}
                        <a target='_blank' class='btn btn-default' href='{$link->getAdminLink('AdminMjifirmainvoice', true, [], ['id_order' => $id_order, 'show' => '1'])|escape:'htmlall':'UTF-8'}'>Zobacz fakturę</a>
                    {/if}
                </td>
            </tr>
        </table>
       
</div>
