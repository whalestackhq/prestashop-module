{*
*  @author 2022 Whalestack
*  @copyright 2022 Whalestack
*  @license https://www.apache.org/licenses/LICENSE-2.0
*}
<form method="post" action="{$action|escape:'htmlall':'UTF-8'}">
    <div style="padding-left:27px">
        {if $displayLogo}
            <img src="../modules/whalestack/logo.png" style="float:left; margin-right:15px;" height="40">
        {/if}
        <p>
            {l s='Pay with Bitcoin, stablecoins and other cryptocurrencies' mod='whalestack'}
            <br />
            {l s='Securely processed by [1]Whalestack.com[/1]' tags=['<a href="https://www.whalestack.com" target="_blank">'] mod='whalestack'}
        <p>
    </div>
</form>