<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<div class="wrapper-blank">
    <header>
        <img src="<?php echo esc_url(plugins_url('img/claspo-logo-black.svg', dirname(__FILE__))); ?>" alt="">
        <a href="https://my.claspo.io/dashboard-ui/#/widgets-list" class="cl-btn-secondary w-auto"><span class="cl-btn-label">Manage widgets</span></a>
    </header>

    <div class="content-page">
        <h1 class="mb-30">Create new widget</h1>
        <div class="cl-widget-tiles">
            <a href="https://my.claspo.io/dashboard-ui/#/widget/from-template/new" class="cl-widget-tile">
                <img class="cl-widget-img" src="<?php echo esc_url(plugins_url('img/template-library.svg', dirname(__FILE__))); ?>" width="140" height="172" alt="">
                <div class="cl-widget-title">Template library</div>
            </a>

            <a href="https://my.claspo.io/dashboard-ui/#/widget/from-scratch/new" class="cl-widget-tile">
                <img class="cl-widget-img scratch" src="data:image/svg+xml,%3csvg%20width='157'%20height='147'%20viewBox='0%200%20157%20147'%20fill='none'%20xmlns='http://www.w3.org/2000/svg'%3e%3crect%20x='0.5'%20y='87.2954'%20width='53'%20height='26'%20rx='13'%20fill='black'/%3e%3cpath%20fill-rule='evenodd'%20clip-rule='evenodd'%20d='M44.5%200.29541H2.5V42.2954H44.5V0.29541Z'%20fill='white'%20fill-opacity='0.5'/%3e%3cpath%20fill-rule='evenodd'%20clip-rule='evenodd'%20d='M42.8208%201.97699H4.18079V40.617H42.8208V1.97699ZM27.4146%2014.2897C27.85%2013.8543%2028.5545%2013.8548%2028.9886%2014.2893L28.9893%2014.29C29.424%2014.7243%2029.424%2015.4285%2028.9893%2015.8628L28.9886%2015.8635C28.5545%2016.298%2027.85%2016.2985%2027.4146%2015.8631C26.9803%2015.4288%2026.9803%2014.724%2027.4146%2014.2897ZM30.3375%2012.9413C29.1581%2011.761%2027.2458%2011.7616%2026.0662%2012.9412C24.8871%2014.1203%2024.8871%2016.0325%2026.0662%2017.2116C27.2459%2018.3913%2029.1584%2018.3918%2030.3377%2017.2113C31.5173%2016.0321%2031.5172%2014.1202%2030.3375%2012.9413ZM21.6905%2019.8616C21.3096%2019.3509%2020.5447%2019.3502%2020.1628%2019.8601L15.4385%2026.169H32.1388L29.3977%2022.706C29.0983%2022.3279%2028.5158%2022.3569%2028.2554%2022.7628C27.2713%2024.2975%2025.0567%2024.3757%2023.9668%2022.9142L21.6905%2019.8616ZM18.6364%2018.717C19.782%2017.1872%2022.0767%2017.1895%2023.2192%2018.7216L25.4955%2021.7741C25.789%2022.1676%2026.3852%2022.1466%2026.6501%2021.7334C27.6171%2020.2255%2029.7811%2020.1179%2030.8929%2021.5224L34.8572%2026.5307C35.0842%2026.8174%2035.1272%2027.2086%2034.9679%2027.5378C34.8087%2027.8669%2034.4752%2028.076%2034.1096%2028.076H13.5332C13.1722%2028.076%2012.8421%2027.8721%2012.6806%2027.5492C12.519%2027.2264%2012.5536%2026.84%2012.77%2026.551L18.6364%2018.717Z'%20fill='black'/%3e%3cpath%20d='M145.789%20122.462C146.389%20121.494%20146.736%20120.352%20146.736%20119.129C146.736%20115.631%20143.9%20112.795%20140.403%20112.795H89.5C83.701%20112.795%2079%20117.496%2079%20123.295V123.962V133.629V134.295C79%20140.094%2083.701%20144.795%2089.5%20144.795H130.761C134.259%20144.795%20137.094%20141.96%20137.094%20138.462C137.094%20137.239%20136.748%20136.097%20136.147%20135.129H148.667C152.164%20135.129%20155%20132.293%20155%20128.795C155%20125.298%20152.164%20122.462%20148.667%20122.462H145.789Z'%20fill='black'%20stroke='white'%20stroke-width='3'%20stroke-linejoin='round'/%3e%3crect%20x='24.5'%20y='31.2954'%20width='97'%20height='97'%20rx='8'%20fill='white'/%3e%3crect%20x='24.5'%20y='31.2954'%20width='97'%20height='97'%20rx='8'%20stroke='%23F3492C'%20stroke-width='4'/%3e%3cpath%20d='M69%2064.7954V75.7954L58%2075.7954C55.7909%2075.7954%2054%2077.5863%2054%2079.7954C54%2082.0045%2055.7909%2083.7954%2058%2083.7954H69V94.7954C69%2097.0046%2070.7909%2098.7954%2073%2098.7954C75.2091%2098.7954%2077%2097.0046%2077%2094.7954V83.7954H88C90.2091%2083.7954%2092%2082.0045%2092%2079.7954C92%2077.5863%2090.2091%2075.7954%2088%2075.7954L77%2075.7954V64.7954C77%2062.5863%2075.2091%2060.7954%2073%2060.7954C70.7909%2060.7954%2069%2062.5863%2069%2064.7954Z'%20fill='black'%20stroke='black'/%3e%3c/svg%3e" width="157" height="147" alt="">
                <div class="cl-widget-title">Start from scratch</div>
            </a>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="claspo_disconnect_script">
            <?php wp_nonce_field('claspo_disconnect_script', 'claspo_nonce'); ?>
            <button type="submit" class="cl-btn-support w-auto mt-auto" style="cursor: pointer;" id="deactivate"><span class="cl-btn-label">Deactivate</span></button>
        </form>
    </div>
</div>
