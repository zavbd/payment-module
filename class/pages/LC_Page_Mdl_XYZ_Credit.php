<?php
require_once(CLASS_PATH . "pages/LC_Page.php");
require_once(MDL_XYZ_CLASS_PATH . 'SC_XYZ_Data.php');

/**
 * クレジット決済情報入力画面 のページクラス.
 *
 * @package Page
 */
class LC_Page_Mdl_XYZ_Credit extends LC_Page {

    var $arrForm;

    // ユニークID
    var $uniqid;

    // エラー内容を格納する配列
    var $arrErr;

    // 決済連携対象の受注番号
    var $order_id;

    // 連携データ管理クラス
    var $objXyzData;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->objXyzData = new SC_XYZ_Data();

        // 送信用データの配列初期化
        $this->initArrParam();

        // テンプレートの設定
        $template = MDL_XYZ_TEMPLATE_PATH . '/credit';
        $template .= SC_MobileUserAgent::isMobile() ? '_mobile.tpl' : '.tpl';
        $this->tpl_mainpage = $template;
        $this->tpl_column_num = 1;
        $this->tpl_title = "XYZ決済 クレジット";

        // 年月プルダウンの初期化
        $objDate = new SC_Date();
        $objDate->setStartYear(date('Y'));
        $objDate->setEndYear(date('Y') + MDL_XYZ_CREDIT_ADD_YEAR);
        $this->arrYear  = $objDate->getZeroYear();
        $this->arrMonth = $objDate->getZeroMonth();

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
        $this->objView = SC_MobileUserAgent::isMobile() ? new SC_MobileView : new SC_SiteView;

        // ユーザユニークIDの取得と購入状態の正当性をチェック
        $this->uniqid = SC_Utils_Ex::sfCheckNormalAccess($objSiteSess, $objCartSess);

        switch($_POST['mode']) {
            // 次へボタン押下時
            case 'send':
                $this->sendMode();
                break;
            // 戻るボタン押下時
            case 'return':
                $this->returnMode();
                exit;
                break;
            // 初回表示
            default:
                $objForm = $this->initParam();
                $this->arrForm = $objForm->getHashArray();
                break;
        }

        $this->objView->assignObj($this);
        $this->objView->display(SITE_FRAME);
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
     * クレジット決済に関する送信データ項目の配列の初期化
     *
     * @param void
     * @return void
     */
    function initArrParam() {
        $this->objXyzData->initArrParam();

        $this->objXyzData->addArrParam("version", 3, MDL_XYZ_TO_ENCODE);
        $this->objXyzData->addArrParam("bill_method", 2, MDL_XYZ_TO_ENCODE);
        $this->objXyzData->addArrParam("kessai_id", 4, MDL_XYZ_TO_ENCODE);
        $this->objXyzData->addArrParam("card_no", 16, MDL_XYZ_TO_ENCODE);
        $this->objXyzData->addArrParam("card_yukokigen", 4, MDL_XYZ_TO_ENCODE);
    }

    /**
     * 決済ステーションへデータを送る
     *
     * @param void
     * @return void
     */
    function sendMode() {
        // フォームパラメータの初期化
        $objForm = $this->initParam();

        $this->arrForm = $objForm->getHashArray();

        // 入力フォームのエラーチェック
        $this->arrErr = $objForm->checkError();

        if (count($this->arrErr) == 0) {
            $arrParam = array();

            // 連携データを取得
            $arrParam =  $this->objXyzData->makeParam($this->uniqid);

            // クレジット決済用連携データを作成
            $arrParam = $this->makeParam($arrParam);

            // 送信データを設定する
            $this->objXyzData->setParam($arrParam);

            // 決済ステーションへ送信
            $arrResponse = $this->objXyzData->sendParam(MDL_XYZ_DATA_LINK_URL);

            // 連携結果を取得
            $res_mode = $this->objXyzData->getMode($arrResponse);
            switch($res_mode) {
                // 完了ページへ遷移
                case 'complete':
                    $this->completeMode();
                    exit;
                    break;
                // 3Dセキュア
                case 'secure':
                    $this->secureMode($arrResponse);
                    break;
                // 決済エラー
                case 'error':
                    $this->dispError($arrResponse);
                    break;
                // 初回表示
                default:
                    break;
            }
        }
        return;
    }

    /**
     * クレジット決済用連携データを設定
     *
     * @param array $arrParam 連携用データ
     * @return array $arrParam クレジット決済用データを追加した連携用データ
     */
    function makeParam($arrParam) {
         // バージョン
        $arrParam['version'] = SC_MobileUserAgent::isMobile() ? MDL_XYZ_DATA_LINK_MOBILE_CREDIT_VERSION : MDL_XYZ_DATA_LINK_PC_VERSION;

        // 決済手段区分
        $arrParam['bill_method'] = MDL_XYZ_CREDIT_BILL_METHOD;

        // 決済種類コード
        $arrParam['kessai_id'] = MDL_XYZ_CREDIT_KESSAI_ID;

        // クレジットカード番号
        $arrParam['card_no'] = $this->arrForm['card_no1']
                              .$this->arrForm['card_no2']
                              .$this->arrForm['card_no3']
                              .$this->arrForm['card_no4'];

        // クレジットカード有効期限
        $arrParam['card_yukokigen'] = $this->arrForm['card_month'] . $this->arrForm['card_year'];

        return $arrParam;
    }

    /**
     * XYZ受注テーブルにデータ送信後、完了ページへ遷移
     *
     * @param void
     * @return void
     */
    function completeMode() {
        $objQuery = new SC_Query();

        // 受注番号を取得
        $orderId = $this->objXyzData->getOrderId();

        // dtb_mdl_xyz_orderに登録
        $sqlval = array('order_id' => $orderId,
                        'credit_status' => MDL_XYZ_CREDIT_STATUS_YOSHIN,
                        'update_date' => 'NOW()');
        $objQuery->insert("dtb_mdl_xyz_order", $sqlval);
   
        // 完了画面へリダイレクト
        $objSiteSess = new SC_SiteSession();
        $objSiteSess->setRegistFlag();

        $isMobile = SC_MobileUserAgent::isMobile();
        if ($isMobile == false) {
            $url = $this->getLocation(URL_SHOP_COMPLETE);
            $this->sendRedirect($url);
        } else {
            $url = $this->getLocation(MOBILE_URL_SHOP_COMPLETE);
            $this->sendRedirect($url);
        }
        exit;
    }

    /**
     * 3Dセキュア連携を行う
     *
     * @param array $arrResponse 決済ステーションからのレスポンスボディ
     * @return void
     */
    function secureMode($arrResponse) {
        $objDb = new SC_Helper_DB_Ex();

        $_SESSION['credit_sessionid'] = $arrResponse['sessionid'];

        $arrData['session'] = serialize($_SESSION);

        // 集計結果を受注一時テーブルに反映
        $objDb->sfRegistTempOrder($_SESSION['site']['uniqid'], $arrData);

        // 送信データを設定
        $this->arrParam['PaReq'] = $arrResponse['PaReq'];
        $this->arrParam['MD'] = $arrResponse['shoporder_no'];
        $this->arrParam['TermUrl'] = SITE_URL . "xyz/credit_secure.php";

        // イシュアURLを送信先とする
        $this->server_url = $arrResponse['issuer_url'];

        // 自動的に画面連携されるようにする
        $this->tpl_onload="document.form1.submit();";
        
        // 送信用ページのテンプレートを設定
        $this->tpl_mainpage = MDL_XYZ_TEMPLATE_PATH . 'page_link.tpl';    

    }

    /**
     * フォームパラメータの初期化
     *
     * @param void
     * @return object SC_FormParam
     */
    function initParam() {
        $objForm = new SC_FormParam();

        $objForm->addParam('カード番号1', 'card_no1', CREDIT_NO_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objForm->addParam('カード番号2', 'card_no2', CREDIT_NO_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objForm->addParam('カード番号3', 'card_no3', CREDIT_NO_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objForm->addParam('カード番号4', 'card_no4', CREDIT_NO_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objForm->addParam("カード期限年", "card_year", INT_LEN, "n", array("EXIST_CHECK", "MAX_LENGTH_CHECK", "NUM_CHECK"));
        $objForm->addParam("カード期限月", "card_month", INT_LEN, "n", array("EXIST_CHECK", "MAX_LENGTH_CHECK", "NUM_CHECK"));

        $objForm->setParam($_POST);
        $objForm->convParam();
        return $objForm;
    }

    /**
     * 戻るボタンのリダイレクト処理
     *
     * @param void
     * @return void
     */
    function returnMode() {
        $objSiteSess = new SC_SiteSession;
        $objSiteSess->setRegistFlag();

        $isMobile = SC_MobileUserAgent::isMobile();
        $url = $isMobile ? MOBILE_URL_SHOP_CONFIRM : URL_SHOP_CONFIRM;

        $this->sendRedirect($this->getLocation($url), $isMobile);
    }

    /**
     * 決済ステーションから受け取ったエラー情報を、表示用データにする.
     *
     * @param array $arrResponse 決済ステーションからのレスポンスボディ
     * @return void
     */
    function dispError($arrResponse) {
        $objQuery = new SC_Query();

        // order_idをnext_valする
        $this->order_id = $this->objXyzData->getNextOrderId();

        // dtb_order_tempを更新
        $sqlval = array('order_id' => $this->order_id);
        $where = "order_temp_id = ?";
        $objQuery->update("dtb_order_temp", $sqlval, $where, array($this->uniqid));

        // 結果内容
        $this->arrErr['res'] = mb_convert_encoding($arrResponse['res'], "UTF-8", "auto");
        // 結果コード
        $this->arrErr['rescd'] = $arrResponse['rescd'];
    }
}
?>
