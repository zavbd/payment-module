<?php
// {{{ requires
require_once(CLASS_PATH . "pages/LC_Page.php");
require_once(MODULE_PATH . 'mdl_xyz/inc/include.php');
require_once(MODULE_PATH . 'mdl_xyz/class/SC_Mdl_XYZ.php');
require_once(MODULE_PATH . 'mdl_xyz/class/SC_XYZ.php');
/**
 * クレジット請求管理 のページクラス.
 *
 * @package Page
 * @author XYZ CO.,LTD.
 * @version $Id$
 */
class LC_Page_Mdl_XYZ_Admin_Order_Credit extends LC_Page {

    // 請求確定連携データの配列
    var $arrParam;

    // エラー内容を格納する配列
    var $arrErr;

    // }}}
    // {{{ functions

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->tpl_mainpage = MODULE_PATH . 'mdl_xyz/templates/admin/order/credit.tpl';
        $this->tpl_subnavi = TEMPLATE_DIR . 'admin/order/subnavi.tpl';
        $this->tpl_mainno = 'order';
        $this->tpl_subno = 'credit';

        $masterData = new SC_DB_MasterData_Ex();
        $this->arrORDERSTATUS = $masterData->getMasterData("mtb_order_status");

        $this->arrCreditStatus = array("0" => "--請求確定--",
                                       "1" => "未確定",
                                       "2" => "確定済み");
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $this->objXyz = new SC_XYZ();
        $objView = new SC_AdminView();
        $objSess = new SC_Session();

        // 認証可否の判定
        $objSess = new SC_Session();
        SC_Utils_Ex::sfIsSuccess($objSess);

        $this->arrForm = $_POST;

        switch ($this->arrForm['mode']){
            case 'send':
                $this->lfSendMode();
                break;
            default:
                break;
        }

        //ステータス情報
        $this->SelectedStatus = $this->arrForm['status'];
        //検索結果の表示
        $this->lfGetCreditData($this->arrForm['status'], $this->arrForm['search_pageno']);

        $objView->assignobj($this);
        $objView->display(MAIN_FRAME);
    }
    /**
     * デストラクタ.
     *
     * @return void
     */
    function destroy() {
        parent::destroy();
    }

    /*
     * クレジット請求一覧の取得
     */
    function lfGetCreditData($status,$pageno){
        $objQuery = new SC_Query();

        $select ="*";
        $from = "dtb_mdl_xyz_order INNER JOIN dtb_order USING(order_id)";
        $where = "del_flg = 0";


        if ($status == MDL_XYZ_CREDIT_STATUS_YOSHIN || $status == MDL_XYZ_CREDIT_STATUS_KAKUTEI) {
            $where .= " AND credit_status = ?";
            $linemax = $objQuery->count($from, $where, array($status));
        } else {
            $linemax = $objQuery->count($from);
        }
        $this->tpl_linemax = $linemax;

        // ページ送りの処理
        $page_max = ORDER_STATUS_MAX;

        // ページ送りの取得
        $objNavi = new SC_PageNavi($pageno, $linemax, $page_max, "fnNaviSearchOnlyPage", NAVI_PMAX);
        $this->tpl_strnavi = $objNavi->strnavi;      // 表示文字列
        $startno = $objNavi->start_row;

        $this->tpl_pageno = $pageno;

        // 取得範囲の指定(開始行番号、行数のセット)
        $objQuery->setlimitoffset($page_max, $startno);

        //表示順序
        $order = "dtb_mdl_xyz_order.order_id DESC";
        $objQuery->setorder($order);

        //検索結果の取得
        if ($status == MDL_XYZ_CREDIT_STATUS_YOSHIN || $status == MDL_XYZ_CREDIT_STATUS_KAKUTEI) {
            $this->arrCreditData = $objQuery->select($select, $from, $where, array($status));
        } else {
            $this->arrCreditData = $objQuery->select($select, $from, $where);
        }
    }

    /**
     * 決済ステーションへデータを送る
     *
     * @param void
     * @return void
     */
    function lfSendMode() {
        // エラーチェック
        $this->errMsg = $this->lfErrCheck();

        if (strlen($this->errMsg) <= 0) {
            // 送信用データの配列初期化
            $this->lfInitArrParam();

            // 連携データを作成
            $this->lfMakeParam($this->arrForm['order_id']);

            // 送信データを設定する
            $this->objXyz->setParam($this->arrParam);

            // 決済ステーションへ送信
            $arrResponse = $this->objXyz->sendParam(MDL_XYZ_CREDIT_KAKUTEI_LINK_URL);

            // 連携結果を取得
            $res_mode = $this->objXyz->getMode($arrResponse);
            switch($res_mode) {
                // 完了処理
                case 'complete':
                    $this->lfCompleteMode();
                    break;
                // エラー
                case 'error':
                    $this->lfDispError($arrResponse);
                    break;
                default:
                    break;
            }
        }
        
    }

    /**
     * クレジット決済以外の受注の場合、エラーメッセージを設定する
     */
    function lfErrCheck() {
        $objQuery = new SC_Query();
        $errMsg = "";

        $col = "memo01";
        $from = "dtb_payment";
        $where = "payment_id = (SELECT payment_id FROM dtb_order WHERE order_id = ?);";

        $payment = $objQuery->select($col, $from, $where, array($this->arrForm['order_id']));

        if ($payment[0]['memo01'] != MDL_XYZ_CREDIT_BILL_METHOD) {
            $errMsg = "クレジット決済以外の受注です。";
        }
        return $errMsg;
    }

    /**
     * 決済ステーションから受け取ったエラー情報を、表示用データにする.
     *
     * @param array $arrResponse 決済ステーションからのレスポンスボディ
     * @return void
     */
    function lfDispError($arrResponse) {
        // 結果内容
        $this->arrErr['res'] = mb_convert_encoding($arrResponse['res'], "UTF-8", "auto");
        // 結果コード
        $this->arrErr['rescd'] = $arrResponse['rescd'];
    }

    /**
     * 請求確定処理を行う
     *
     * @param void
     * @return void
     */
    function lfCompleteMode() {
        $objQuery = new SC_Query();
        
        $sqlval = array();
        $sqlval['credit_status'] = MDL_XYZ_CREDIT_STATUS_KAKUTEI;
        $sqlval['update_date'] = "NOW()";

        $objQuery->update("dtb_mdl_xyz_order", $sqlval, "order_id = ?", array($this->arrForm['order_id']));
    }

    /**
     * 全決済の共通項目の連携データを設定する
     *
     * @param unknown_type $order_id 受注番号
     * @return array $arrParam
     */
    function lfMakeParam ($order_id) {
        $objQuery = new SC_Query();

        // バージョン
        $this->arrParam['version'] = MDL_XYZ_KAKUTEI_LINK_CREDIT_VERSION;

        // 決済手段区分
        $this->arrParam['bill_method'] = MDL_XYZ_CREDIT_BILL_METHOD;

        // 決済種類コード
        $this->arrParam['kessai_id'] = MDL_XYZ_CREDIT_KESSAI_ID;

        // 請求番号
        $this->arrParam['shoporder_no'] = str_pad($this->arrForm['order_id'], 17, "0", STR_PAD_LEFT);

        // 請求金額
        $this->arrParam['seikyuu_kingaku'] = $this->arrForm['payment_total'];

        // モジュールマスタからデータを取得
        $payment_id = $objQuery->select("payment_id", "dtb_order", "order_id = ?", array($order_id));
        $arrModule = $this->objXyz->getModuleMasterData($payment_id[0]['payment_id']);

        // 契約コード
        $this->arrParam['shop_cd'] = $arrModule['shop_cd'];

        // 収納企業コード
        $this->arrParam['syuno_co_cd'] = $arrModule['syuno_co_cd'];

        // ショップパスワード
        $this->arrParam['shop_pwd'] = $arrModule['shop_pwd'];
    }

    /**
     * クレジット請求確定連携の送信データ項目の配列の初期化
     *
     * @param void
     * @return void
     */
    function lfInitArrParam() {
        $this->objXyz->addArrParam("version", 3);
        $this->objXyz->addArrParam("bill_method", 2);
        $this->objXyz->addArrParam("kessai_id", 4);
        $this->objXyz->addArrParam("shop_cd", 7);
        $this->objXyz->addArrParam("syuno_co_cd", 8);
        $this->objXyz->addArrParam("shop_pwd", 20);
        $this->objXyz->addArrParam("shoporder_no", 17);
        $this->objXyz->addArrParam("seikyuu_kingaku", 13);
    }
}
?>
