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
                    <th><b>{l s='Delete' mod='mjifirma'}</b></th>
                </tr>
            </thead>
            <tr>
                <td><strong>Faktura</strong></td>
                <td>
                    {if empty($invoice)}
                        <a href="{$link->getAdminLink('AdminMjifirmainvoice', true, [], ['id_order' => $id_order, 'kind' => 'vat'])|escape:'htmlall':'UTF-8'}" class="btn btn-default">Wystaw fakturę VAT</a>
                    {else}
                        <a target='_blank' class='btn btn-default' href='{$invoice_url|escape:'htmlall':'UTF-8'}/{$single_invoice['external_id']|escape:'htmlall':'UTF-8'}'>Zobacz fakturę</a>
                    {/if}
                </td>
                <td>
                    {if !empty($invoice)}
                    <a class='btn btn-danger' href='{$link->getAdminLink('AdminMjifirmainvoice', true, [], ['id_order' => $id_order, 'invoice' => 'delete'])|escape:'htmlall':'UTF-8'}'>Usuń fakturę</a>
                    {/if}
                </td>
            </tr>
        </table>
       
</div>
