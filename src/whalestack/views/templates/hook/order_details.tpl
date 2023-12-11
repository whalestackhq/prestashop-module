{*
*  @author 2022 Whalestack
*  @copyright 2022 Whalestack
*  @license https://www.apache.org/licenses/LICENSE-2.0
*}
<p>Whalestack Payment Details:<p>

{if !$display}
    <p>No Whalestack payment received yet.</p>
{/if}

{if $display == 'paid'}
<p><span style="color:#079047">Whalestack payment was successfully processed. Find payment details <a href="https://www.whalestack.com/en/payment/checkout-id/{$checkoutId|escape:'htmlall':'UTF-8'}" target="_blank">here</a>.</span></p>
{/if}

{if $display == 'underpaid'}
<p><span style="color:#cc292f">Whalestack payment was underpaid by customer. See details and options to resolve it <a href="https://www.whalestack.com/en/unresolved-charge/checkout-id/{$checkoutId|escape:'htmlall':'UTF-8'}" target="_blank">here</a>.</span></p>
{/if}

{if $display == 'refunded'}
    <p><span style="color:#007cba;">Order amount was refunded successfully to customer. See details <a href="https://www.whalestack.com/en/refund/{$refundId|escape:'htmlall':'UTF-8'}" target="_blank">here</a>.</span></p>
{/if}




