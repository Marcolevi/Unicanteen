<?php
// Shared footer for signed-in customer, seller, and administrator pages.
if (!defined('UNICANTEEN_COMMON_FOOTER')) {
    define('UNICANTEEN_COMMON_FOOTER', true);

    function unicanteenCommonFooter(string $html): string {
        if (stripos($html, '</body>') === false) return $html;

        // Remove older page-specific footer copies before adding the shared one.
        $html = preg_replace('/<div\s+class="footer-wrapper"[^>]*>\s*<footer\b[\s\S]*?<\/footer>\s*<\/div>/i', '', $html);
        $html = preg_replace('/<footer\b[\s\S]*?<\/footer>/i', '', $html);

        $style = '<style id="unicanteen-footer-style">html,body{min-height:100%}#unicanteen-footer{margin-top:40px;background:#0d131d;color:#cbd5e1;padding:32px 24px 20px;border-top:2px solid rgba(37,99,235,.25);font-family:Poppins,Arial,sans-serif;clear:both}#unicanteen-footer .uf-content{max-width:980px;margin:0 auto 24px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:32px}#unicanteen-footer h4{margin:0 0 12px;color:#fff;font:700 15px Montserrat,Arial,sans-serif}#unicanteen-footer a,#unicanteen-footer span{display:block;margin:8px 0;color:#cbd5e1;text-decoration:none;font-size:13px}#unicanteen-footer a:hover{color:#60a5fa}#unicanteen-footer .uf-bottom{max-width:980px;margin:auto;padding-top:18px;border-top:1px solid rgba(255,255,255,.1);text-align:center;color:#94a3b8;font-size:13px}@media(max-width:760px){#unicanteen-footer{padding:28px 18px 18px}#unicanteen-footer .uf-content{grid-template-columns:1fr;gap:20px}}</style>';
        $hasSidebar = preg_match('/class=[\'\"][^\'\"]*\bsidebar\b/i', $html) === 1;
        $layoutStyle = $hasSidebar
            ? '<style>body{flex-wrap:wrap!important}#unicanteen-footer{box-sizing:border-box!important;flex:0 0 calc(100% - 230px)!important;width:calc(100% - 230px)!important;max-width:none!important;margin:40px 0 0 230px!important}@media(max-width:850px){#unicanteen-footer{flex-basis:100%!important;width:100%!important;margin-left:0!important}}</style>'
            : '<style>#unicanteen-footer{box-sizing:border-box!important;width:100%!important;max-width:none!important}</style>';
        $footer = '<footer id="unicanteen-footer"><div class="uf-content"><section><h4>About UniCanteen</h4><a href="about_us.php">About Us</a><a href="news.php">News</a></section><section><h4>Support</h4><a href="#faq">FAQ</a><a href="#help">Help Center</a></section><section><h4>Contact Info</h4><span>support@unicanteen.edu</span><span>Mon–Fri, 7AM–6PM</span></section></div><div class="uf-bottom">© 2026 UniCanteen System. All rights reserved.</div></footer>';
        $html = str_ireplace('</head>', $style . $layoutStyle . '</head>', $html);
        return str_ireplace('</body>', $footer . '</body>', $html);
    }

    ob_start('unicanteenCommonFooter');
}
