<?php
require_once(CLASS_PATH . "pages/LC_Page.php");
require_once(MDL_XYZ_CLASS_PATH . 'SC_XYZ_Page.php');

/**
 * ネットバンク決済情報入力画面 のページクラス.
 *
 * @package Page
 */
class LC_Page_Mdl_XYZ_Netbank extends LC_Page {

    // 画面連携管理クラス
    var $objXyzPage;

    // 送信データ用配列
    var $arrParam;

    // 送信先URL
    var $server_url;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->objXyzPage = new SC_XYZ_Page();

        $this->tpl_onload="document.form1.submit();";
        $this->tpl_title = "決済情報送信";

        // 送信用データの配列初期化
        $this->initArrParam();

        // PC・モバイルによって送信先URL・テンプレートを切り替え
        if (SC_MobileUserAgent::isMobile() == true) {
            $this->server_url = MDL_XYZ_PAGE_LINK_MOBILE_URL;
            $this->tpl_mainpage = MDL_XYZ_TEMPLATE_PATH . 'page_link_mobile.tpl';
        } else {
            $this->server_url = MDL_XYZ_PAGE_LINK_PC_URL;
            $this->tpl_mainpage = MDL_XYZ_TEMPLATE_PATH . 'page_link.tpl';
        }

        $this->allowClientCache();
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $objCartSess = new SC_CartSession();
        $objSiteSess = new SC_SiteSession();
        $objView = SC_MobileUserAgent::isMobile() ? new SC_MobileView : new SC_SiteView;

        // ユーザユニークIDの取得と購入状態の正当性をチェック
        $this->uniqid = SC_Utils_Ex::sfCheckNormalAccess($objSiteSess, $objCartSess);

        // 連携データを取得
        $arrParam =  $this->objXyzPage->makeParam($this->uniqid);

        // 決済用連携データを作成
        $this->arrParam = $this->makeParam($arrParam);

        // 送信データを設定する
        $this->objXyzPage->setParam($this->arrParam);

        // 送信データのサイズ調整・エンコード変換を行う
        $this->arrParam = $this->objXyzPage->convParamStr();

        // 送信データをログ出力
        $this->objXyzPage->printLog($this->arrParam);

        // セッションカート内の商品を削除する。
        $objCartSess->delAllProducts();
        // 注文一時IDを解除する。
        $objSiteSess->unsetUniqId();

        $objView->assignObj($this);
        if (SC_MobileUserAgent::isMobile() == true) {
            $objView->display(SITE_FRAME);
        } else {
            $objView->display(MODULE_PATH . "mdl_xyz/templates/page_link.tpl");
        }
    }

    /**
     * デストラクタ.
     *
     * @return void
     */
    function destroy() {
        parent::destroy();
    }

    /**
     * ネットバンク決済に関する送信データ項目の配列の初期化
     */
    function initArrParam() {
        $this->objXyzPage->addArrParam("bill_method", 2, MDL_XYZ_TO_ENCODE);
        $this->objXyzPage->addArrParam("kessai_id", 4, MDL_XYZ_TO_ENCODE);
    }

    /**
     * ネットバンク決済用連携データを設定
     *
     */
    function makeParam($arrParam) {
        // 決済手段区分
        $arrParam['bill_method'] = MDL_XYZ_NETBUNK_BILL_METHOD;

        // 決済種類コード。
        $arrParam['kessai_id'] = MDL_XYZ_NETBUNK_KESSAI_ID;

        return $arrParam;
    }
}
?>