<?php
// {{{ requires
require_once(CLASS_PATH . "pages/LC_Page.php");

/**
 * ご注文完了 のページクラス.
 *
 * @package Page
 * @author XYZ CO.,LTD.
 * @version $Id:
 */
class LC_Page_Mdl_XYZ_Shopping_Complete extends LC_Page {

    // }}}
    // {{{ functions

     /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $template = MODULE_PATH . "mdl_xyz/templates/complete";
        $template .= SC_MobileUserAgent::isMobile() ? '_mobile.tpl' : '.tpl';
        $this->tpl_mainpage = $template;
        $this->tpl_title = "ご注文完了";
        $this->tpl_column_num = 1;
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $objView = SC_MobileUserAgent::isMobile() ? new SC_MobileView : new SC_SiteView;
        $objSiteInfo = $objView->objSiteInfo;
        $this->arrInfo = $objSiteInfo->data;

        $objView->assignobj($this);
        $objView->display(SITE_FRAME);
    }

    /**
     * デストラクタ.
     *
     * @return void
     */
    function destroy() {
        parent::destroy();
    }
}
?>
