{*
*  @author 2022 COINQVEST
*  @copyright 2022 COINQVEST
*  @license https://www.apache.org/licenses/LICENSE-2.0
*}
<form method="post" action="{$action|escape:'htmlall':'UTF-8'}">
    <div style="padding-left:27px">
        {if $displayLogo}
            <img src="../modules/coinqvest/logo.png" style="float:left; margin-right:15px;" height="60">
        {/if}
        <p>
            {l s='Pay with Bitcoin, Ethereum, Litecoin or other digital currencies.' mod='coinqvest'}
            <br />
            {l s='Securely processed by [1]COINQVEST.com[/1]' tags=['<a href="https://www.coinqvest.com" target="_blank">'] mod='coinqvest'}
        <p>
    </div>
</form>