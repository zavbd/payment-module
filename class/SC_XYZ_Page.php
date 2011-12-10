<?php
require_once(MDL_XYZ_CLASS_PATH . 'SC_XYZ.php');

class SC_XYZ_Page extends SC_XYZ {

    // コンストラクタ
    function SC_XYZ_Page() {
        parent::SC_XYZ();
        $this->init();
    }

    // 初期化
    function init() {
        $this->initArrParam();
    }

    function initArrParam() {
        parent::initArrParam();
        $this->addArrParam("version", 3);
        $this->addArrParam("shop_link", 256);
        $this->addArrParam("shop_res_link", 256);
        $this->addArrParam("shop_error_link", 256);
        $this->addArrParam("hakkou_kbn", 1);
        $this->addArrParam("yuusousaki_kbn", 1);
        $this->addArrParam("riyou_nengetsu", 6);
        $this->addArrParam("seikyuu_nengetsu", 6);

    }

    /**
     * 送信用配列に格納されている情報に合わせて、サイズ調整を行う
     */
    function convParamStr () {
        $arrParam =  parent::convParamStr();
        mb_convert_variables('UTF-8', 'auto', $arrParam);

        // 送信データを全角カタカナで送る必要がある項目があるが
        // モバイルの場合SC_Helper_Mobileでob_startを使って、
        // カタカナを半角にする処理が入っているため、そのまま送るとエラーになってしまう。
        // そのため、ob_end_flush()つかって一旦バッファを無くし、再度必要なものだけ設定する
        if (SC_MobileUserAgent::isMobile() == true) {
            while(ob_get_level()) {
                 ob_end_flush();
            }
            mb_http_output('SJIS-win');
            ob_start(array('SC_MobileEmoji', 'handler'));
            ob_start('mb_output_handler');
        }

        return $arrParam;
    }

    /**
     * 連携データを設定する
     *
     * @param unknown_type $uniqid
     */
    function makeParam ($uniqid) {
        $arrParam = array();

        // 全決済共通の連携データを設定
        $arrParam = parent::makeParam($uniqid);

        if (strlen($arrParam['hakkou_kbn']) <= 0) $arrParam['hakkou_kbn'] = "";
        if (strlen($arrParam['yuusousaki_kbn']) <= 0) $arrParam['yuusousaki_kbn'] = "";

        // バージョンを設定
        $arrParam['version'] = SC_MobileUserAgent::isMobile() ? MDL_XYZ_PAGE_LINK_MOBILE_VERSION : MDL_XYZ_PAGE_LINK_PC_VERSION;

        // 遷移先URLを設定
        $arrParam['shop_link'] = SC_MobileUserAgent::isMobile() ? MOBILE_SITE_URL . "xyz/complete.php?PHPSESSID=".$_GET['PHPSESSID']:
                                                                  SITE_URL . "xyz/complete.php";

        // 結果通知URL
        $arrParam['shop_res_link'] = SITE_URL . "xyz/order_recv.php";

        // エラー時遷移先URL
        $arrParam['shop_error_link'] = SC_MobileUserAgent::isMobile() ? MOBILE_SITE_URL:SITE_URL;

        // 利用年月
        $arrParam['riyou_nengetsu'] = date('Ym');

        // 請求年月
        $arrParam['seikyuu_nengetsu'] = date('Ym');

        return $arrParam;
    }

    function getArrParam () {
        return $this->arrParam;
    }
}
?>
