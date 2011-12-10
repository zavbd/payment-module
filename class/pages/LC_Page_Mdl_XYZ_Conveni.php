<?php
require_once(CLASS_PATH . "pages/LC_Page.php");
require_once(MDL_XYZ_CLASS_PATH . 'SC_XYZ_Data.php');

/**
 * コンビニ決済（番号受付）情報入力画面 のページクラス.
 *
 * @package Page
 */
class LC_Page_Mdl_XYZ_Conveni extends LC_Page {

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

        global $arrCONVENI;
        $this->arrCONVENI = $arrCONVENI;

        // 送信用データの配列初期化
        $this->initArrParam();

        // テンプレートの設定
        $template = MDL_XYZ_TEMPLATE_PATH . '/conveni';
        $template .= SC_MobileUserAgent::isMobile() ? '_mobile.tpl' : '.tpl';
        $this->tpl_mainpage = $template;
        $this->tpl_column_num = 1;
        $this->tpl_title = "XYZ決済 コンビニ（番号方式）";

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

        switch($_POST['mode']) {
            // 次へボタン押下時
            case 'send':
                $this->sendMode();
                break;
            // 戻るボタン押下時
            case 'return':
                $this->returnMode();
                exit; // リダイレクトするためexitする
                break;
            // 初回表示
            default:
                $objForm = $this->initParam();
                $this->arrForm = $objForm->getFormParamList();
                $this->arrForm['conveni']['value'] = MDL_XYZ_CONVENI_SEVENELEVEN_KESSAI_ID;
                break;
        }

        $objView->assignObj($this);
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

    /**
     * コンビニ決済に関する送信データ項目の配列の初期化
     */
    function initArrParam() {
        $this->objXyzData->initArrParam();

        $this->objXyzData->addArrParam("version", 3, MDL_XYZ_TO_ENCODE);
        $this->objXyzData->addArrParam("bill_method", 2, MDL_XYZ_TO_ENCODE);
        $this->objXyzData->addArrParam("kessai_id", 4, MDL_XYZ_TO_ENCODE);
    }

    /**
     * 決済ステーションへデータを送る
     *
     */
    function sendMode() {
        // フォームパラメータの初期化
        $objForm = $this->initParam();

        $this->arrForm = $objForm->getFormParamList();

        // 入力フォームのエラーチェック
        $this->arrErr = $objForm->checkError();

        if (count($this->arrErr) == 0) {
            $arrParam = array();

            // 連携データを取得
            $arrParam =  $this->objXyzData->makeParam($this->uniqid);

            // コンビニ決済用連携データを作成
            $arrParam = $this->makeParam($arrParam);

            // 送信データを設定する
            $this->objXyzData->setParam($arrParam);

            // 決済ステーションへ送信
            $arrResponse = $this->objXyzData->sendParam(MDL_XYZ_DATA_LINK_URL);

            // 連携結果を取得
            $res_mode = $this->objXyzData->getMode($arrResponse);
            switch($res_mode) {
                // 決済処理を行う
                case 'complete':
                    $this->completeMode($arrResponse);
                    exit;
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
    }

    /**
     * コンビニ決済用連携データを設定
     *
     */
    function makeParam($arrParam) {
         // バージョン
        $arrParam['version'] = SC_MobileUserAgent::isMobile() ? MDL_XYZ_DATA_LINK_MOBILE_CONVENI_VERSION : MDL_XYZ_DATA_LINK_PC_VERSION;

        // 決済手段区分
        $arrParam['bill_method'] = MDL_XYZ_CONVENI_NUMBER_BILL_METHOD;
 
        // 決済種類コード。
        $arrParam['kessai_id'] = $this->arrForm['conveni']['value'];

        return $arrParam;
    }

    /**
     * 完了画面へリダイレクトする
     *
     */
    function completeMode($arrResponse) {
        $objQuery = new SC_Query();
        $arrGuide = array();

        // コンビニの支払情報を完了画面表示のためにＤＢ更新
        switch($arrResponse['kessai_id']) {
            case MDL_XYZ_CONVENI_SEVENELEVEN_KESSAI_ID:
                $arrGuide['title']['value'] = "1";
                $arrGuide['title']['name'] = "セブンイレブンでのお支払";
                $arrGuide['haraidashi_no1']['name'] = "払込票番号";
                $arrGuide['haraidashi_no1']['value'] = $arrResponse['haraidashi_no1'];
                $arrGuide['haraidashi_no2']['name'] = "払込票URL";
                $arrGuide['haraidashi_no2']['value'] = $arrResponse['haraidashi_no2'];
                break;
            case MDL_XYZ_CONVENI_LAWSON_KESSAI_ID:
                $arrGuide['title']['value'] = "1";
                $arrGuide['title']['name'] = "ローソンでのお支払";
                $arrGuide['haraidashi_no1']['name'] = "支払受付番号";
                $arrGuide['haraidashi_no1']['value'] = $arrResponse['haraidashi_no1'];
                break;
            case MDL_XYZ_CONVENI_SEICOMART_KESSAI_ID:
                $arrGuide['title']['value'] = "1";
                $arrGuide['title']['name'] = "セイコーマートでのお支払";
                $arrGuide['haraidashi_no1']['name'] = "支払受付番号";
                $arrGuide['haraidashi_no1']['value'] = $arrResponse['haraidashi_no1'];
                break;
            case MDL_XYZ_CONVENI_FAMILYMART_KESSAI_ID:
                $arrGuide['title']['value'] = "1";
                $arrGuide['title']['name'] = "ファミリーマートでのお支払";
                $arrGuide['haraidashi_no1']['name'] = "企業コード";
                $arrGuide['haraidashi_no1']['value'] = $arrResponse['haraidashi_no1'];
                $arrGuide['haraidashi_no2']['name'] = "注文番号";
                $arrGuide['haraidashi_no2']['value'] = $arrResponse['haraidashi_no2'];
                break;
            case MDL_XYZ_CONVENI_CIRCLEKSUNKUS_KESSAI_ID:
                $arrGuide['title']['value'] = "1";
                $arrGuide['title']['name'] = "サークルＫ・サンクスでのお支払";
                $arrGuide['haraidashi_no1']['name'] = "オンライン決済番号";
                $arrGuide['haraidashi_no1']['value'] = $arrResponse['haraidashi_no1'];
                break;
            default:
                break;
        }
        $sqlval['memo02'] = serialize($arrGuide);
        
        $where = "order_temp_id = ?";
        $objQuery->update("dtb_order_temp", $sqlval, $where, array($this->uniqid));

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
     * フォームパラメータの初期化
     *
     * @return SC_FormParam
     */
    function initParam() {
        $objForm = new SC_FormParam();

        $objForm->addParam("コンビニの選択", "conveni", INT_LEN, "n", array('EXIST_CHECK', "MAX_LENGTH_CHECK", "NUM_CHECK"));

        $objForm->setParam($_POST);
        $objForm->convParam();
        return $objForm;
    }

    /**
     * 戻るボタンのリダイレクト処理
     *
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

        return;
    }
}
?>