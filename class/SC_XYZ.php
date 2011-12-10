<?php
require_once DATA_PATH . 'module/Request.php';
require_once(MODULE_PATH . 'mdl_xyz/class/SC_Mdl_XYZ.php');

// 消費税
define('MDL_XYZ_TAX', '1.05');
// 請求内容（漢字）
define('MDL_XYZ_SEIKYUU_NAME', 'お申込代金');
// 請求内容（カナ）
define('MDL_XYZ_SEIKYUU_KANA', 'オモウシコミダイキン');
// 商品名。商品数が２０以上の場合。
define('MDL_XYZ_GOODS_NAME_OHER', 'その他');
// 商品の項目数上限
define('MDL_XYZ_PRODUCT_CNT_MAX', 20);
// 商品名の上限バイト数
define('MDL_XYZ_GOODS_NAME_MAX_LEN', 100);
// 顧客名の上限バイト数
define('MDL_XYZ_CUSTOMER_NAME_MAX_LEN', 60);
// 顧客住所１項目の上限バイト数
define('MDL_XYZ_ADDR_NAME_MAX_LEN', 50);
// 商品数の上限
define('MDL_XYZ_PRODUCT_QUANTITY_LIMIT', 1000);
// 商品数が上限を超えた時の
define('MDL_XYZ_PRODUCT_QUANTITY_OVER', 999);

class SC_XYZ {

    // 連携データ用
    var $arrParam;

    // 受注番号
    var $order_id;

    // セッションのカート情報を格納する配列
    var $arrCart;

    var $objQuery;

    /**
     *  コンストラクタ
     */
    function SC_XYZ() {
        SC_XYZ::init();
    }

    /**
     *  初期化
     */
    function init() {
        $this->objQuery = new SC_Query();

        SC_XYZ::clearArrParam();

        $masterData = new SC_DB_MasterData();
        $this->arrPref = $masterData->getMasterData("mtb_pref", array("pref_id", "pref_name", "rank"));
    }

    /**
     * $this->arrParamを初期化
     *
     * @param void
     * @return void
     */
    function clearArrParam() {
        $this->arrParam = array();
    }

    /**
     * 連携データ用配列を初期化
     *
     * @param void
     * @return void
     */
    function initArrParam() {
        SC_XYZ::addArrParam("shop_cd", 7);
        SC_XYZ::addArrParam("syuno_co_cd", 8);
        SC_XYZ::addArrParam("shop_pwd", 20);
        SC_XYZ::addArrParam("shoporder_no", 17);
        SC_XYZ::addArrParam("seikyuu_kingaku", 13);
        SC_XYZ::addArrParam("shouhi_tax", 13);
        SC_XYZ::addArrParam("bill_no", 14);
        SC_XYZ::addArrParam("bill_name", MDL_XYZ_CUSTOMER_NAME_MAX_LEN);
        SC_XYZ::addArrParam("bill_kana", MDL_XYZ_CUSTOMER_NAME_MAX_LEN);
        SC_XYZ::addArrParam("bill_zip", 8);
        SC_XYZ::addArrParam("bill_phon", 14);
        SC_XYZ::addArrParam("bill_mail", 256);
        SC_XYZ::addArrParam("bill_mail_kbn", 1);
        SC_XYZ::addArrParam("seiyaku_date", 8);
        SC_XYZ::addArrParam("seikyuu_name", 100);
        SC_XYZ::addArrParam("seikyuu_kana", 48);
        SC_XYZ::addArrParam("bill_adr", 250);
        for ($i=1; $i <= MDL_XYZ_PRODUCT_CNT_MAX; $i++) {
            SC_XYZ::addArrParam('goods_name_' . $i, 100);
            SC_XYZ::addArrParam('unit_price_' . $i, 11);
            SC_XYZ::addArrParam('quantity_' . $i, 3);
        }
    }

    /**
     * 各項目ごとにサイズと文字コード情報を配列として格納する
     *
     * @param string $key 項目名
     * @param string $size 該当項目の文字サイズ（バイト数）
     * @param string $encode 変換文字コード
     * @return void
     */
    function addArrParam($key, $size, $encode = "SJIS-win") {
        $this->arrParam[$key]['size'] = $size;
        $this->arrParam[$key]['encode'] = $encode;
    }

    /**
     * 項目エレメント名をセットする
     *
     * @param string $key 項目キー
     * @param string $value 項目名
     * @return void
     */
    function setValArrParam($key, $value) {
        $this->arrParam[$key]['value'] = $value;
    }

    /**
     * 連携データを設定する
     *
     * @param array $arrParam 連携データ用配列
     * @return void
     */
    function setParam($arrParam) {
        foreach ($arrParam as $key => $val) {
            SC_XYZ::setValArrParam($key, $val);
        }
    }

    /**
     * 全決済の共通項目の連携データを設定する
     *
     * @param unknown_type $uniqid
     * @return array $arrParam
     */
    function makeParam ($uniqid) {
        $arrParam = array();

        // dtb_order_tempの情報を取得
        $arrOrderTemp = $this->getOrderTemp($uniqid);

        // 請求番号
        $this->order_id = $arrOrderTemp['order_id'];
        $arrParam['shoporder_no'] = str_pad($this->order_id, 17, "0", STR_PAD_LEFT);

        // 請求金額
        $arrParam['seikyuu_kingaku'] = $arrOrderTemp['payment_total'];
        // 内消費税
        $arrParam['shouhi_tax'] = floor($arrOrderTemp['payment_total']-($arrOrderTemp['payment_total']/MDL_XYZ_TAX));
        // 成約日
        $arrParam['seiyaku_date'] = date('Ymd');
        // 請求内容（漢字）
        $arrParam['seikyuu_name'] = MDL_XYZ_SEIKYUU_NAME;
        // 請求内容（カナ）
        $arrParam['seikyuu_kana'] = MDL_XYZ_SEIKYUU_KANA;

        // モジュールマスタの連携データを取得
        $arrParam = array_merge($arrParam, $this->getModuleMasterData($arrOrderTemp['payment_id']));

        // 顧客情報を取得
        $arrParam = array_merge($arrParam, $this->getCustomerData($arrOrderTemp));

        // 商品情報を連携データを取得
        $arrParam = array_merge($arrParam, $this->getProductsData($this->arrCart));

        return $arrParam;
    }

    /**
     * dtb_order.order_idをnextvalして値を渡す
     *
     */
    function getNextOrderId() {
        return $this->objQuery->nextval("dtb_order", "order_id");
    }

    /**
     * dtb_order_tempからセッションデータを取得
     *
     * @param  string $uniqid
     * @return array $arrOrderTemp session情報
     */
    function getOrderTemp($uniqid) {
        // $uniqidをキーにdtb_order_tempを取得
        $arrOrderTemp = $this->objQuery->select("*","dtb_order_temp", "order_temp_id = ?", array($uniqid));

        // dtb_order_tempのseissionをunserializeし$arrOrderSessionに格納
        $arrOrderSession = unserialize($arrOrderTemp[0]['session']);

        $this->arrCart = $arrOrderSession['cart'];

        // 復元したセッション情報とdtb_order_tempをマージ
        $arrOrderTemp = array_merge($arrOrderTemp, $arrOrderSession);

        return $arrOrderTemp[0];
    }

    /**
     * モジュールマスタからデータを取得
     *
     * @param integer $payment_id 支払番号
     * @return array
     */
    function getModuleMasterData($payment_id) {
        $arrModule = array();

        // payment_idをキーにして、モジュールデータを取得
        $col = "sub_data";
        $from = "dtb_module";
        $where = "module_code = (SELECT module_code FROM dtb_payment WHERE payment_id = ?)";
        $arrModule = $this->objQuery->select($col, $from, $where, array($payment_id));

        // 取得したモジュールデータをunserializeして復元
        $arrModule = unserialize($arrModule[0]['sub_data']);

        return array('shop_cd' => $arrModule['shop_cd'],
                     'syuno_co_cd' => $arrModule['syuno_co_cd'],
                     'shop_pwd' => $arrModule['shop_pwd'],
                     'hakkou_kbn' => $arrModule['payment_slip_issue'],
                     'yuusousaki_kbn' => $arrModule['payment_slip_destination']
                    );
    }

    /**
     * 顧客名・顧客カナ名・顧客郵便番号・顧客住所
     * 顧客メールアドレス・顧客メールアドレス区分を取得する
     *
     * @param array $arrOrderTemp 受注一時テーブルの内容
     * @return array $arrCustomer 顧客情報を格納したテーブル
     */
    function getCustomerData($arrOrderTemp) {
        $arrCustomer = array();

        // 顧客番号。ゲスト購入の時（customer_id = 0）は空を格納
        $arrCustomer['bill_no'] = ($arrOrderTemp['customer_id'] >0) ? str_pad($arrOrderTemp['customer_id'], 14, "0", STR_PAD_LEFT) : "";

        // 顧客名。英数字を全角にする。
        $arrCustomer['bill_name'] = mb_convert_kana($arrOrderTemp['order_name01'] . $arrOrderTemp['order_name02'], "KVAN");

        // 顧客カナ名。半角カナに変換。
        $arrCustomer['bill_kana'] = mb_convert_kana($arrOrderTemp['order_kana01'] . $arrOrderTemp['order_kana02'], "kan");

        // 顧客郵便番号
        $arrCustomer['bill_zip'] = $arrOrderTemp['order_zip01'] . $arrOrderTemp['order_zip02'];

        // 顧客電話番号
        $arrCustomer['bill_phon'] = $arrOrderTemp['order_tel01'] . $arrOrderTemp['order_tel02'] . $arrOrderTemp['order_tel03'];

        // 顧客メールアドレス
        $arrCustomer['bill_mail'] = $arrOrderTemp['order_email'];

        // 顧客メールアドレス区分(PC:0 モバイル:1)
        $arrCustomer['bill_mail_kbn'] = (SC_Helper_Mobile::gfIsMobileMailAddress($arrOrderTemp['order_email'])) ? 1 : 0; // TODO 定数化する

        // 顧客住所。
        $arrCustomer['bill_adr'] = mb_convert_kana($this->arrPref[$arrOrderTemp['order_pref']] . $arrOrderTemp['order_addr01'] . $arrOrderTemp['order_addr02'], "AKNS");
        $arrCustomer['bill_adr'] = str_replace("－", "―", $arrCustomer['bill_adr']);

        return $arrCustomer;
    }

    /**
     * 復元したセッションからカート内の商品数を取得
     * 商品数が20未満の場合はそのままgoods_name_1～goods_name_20に商品名を入れる。
     * 商品数が21以上の場合は、goods_name_1～goods_name_19には通常通り設定し、
     * goods_name_20には「その他」、quantity_20は'1'、unit_price_20は20以上の送金額を設定
     *
     * @param array $arrCart セッションのカート情報
     * @return array $arrProducts カート内の商品情報
     */
    function getProductsData($arrCart) {
        $arrProducts = array();
        $arrProductsName = array();

        $cnt = 1; // 商品項目数
        $over_limit_price = 0; // 商品20以上の合計価格
        if (count($arrCart) > 0){
            foreach($arrCart as $key => $val) {
                if (is_numeric($key) == true && strlen($arrCart[$key]['id'][0]) > 0) {
                    if ($cnt >= MDL_XYZ_PRODUCT_CNT_MAX) {
                        $over_limit_price += $arrCart[$key]['price'] * $arrCart[$key]['quantity'];
                    }

                    if ($cnt <= MDL_XYZ_PRODUCT_CNT_MAX) {
                        //　商品名を取得
                        $arrProductsName = $this->objQuery->select("name", "dtb_products", "product_id = ?", array($arrCart[$key]['id'][0]));

                        // 文字コード変換後。
                        $arrProducts['goods_name_' . $cnt] = $arrProductsName[0]['name'];

                        // 商品単価。
                        $arrProducts['unit_price_' . $cnt] = $arrCart[$key]['price'];

                        // 商品の数量。数量が1000以上の場合は999とする。
                        if ($arrCart[$i]['quantity'] < MDL_XYZ_PRODUCT_QUANTITY_LIMIT) {
                            $arrProducts['quantity_' . $cnt] = $arrCart[$key]['quantity'];
                        } else {
                            $arrProducts['quantity_' . $cnt] = MDL_XYZ_PRODUCT_QUANTITY_OVER;
                        }
                    }
                    $cnt++;
                }
            }
        }

        // 商品数が20を超えた場合、商品名="その他"・商品単価=[商品数]*[商品単価]・商品数=1とする
        if ($cnt-1 > MDL_XYZ_PRODUCT_CNT_MAX) {
            $arrProducts['goods_name_20'] = MDL_XYZ_GOODS_NAME_OHER;
            $arrProducts['unit_price_20'] = $over_limit_price;
            $arrProducts['quantity_20'] = "1";

        // 商品数が20未満の場合は、初期化して超えた分の送信用配列の商品情報部分を削除
        } else {
            for ($i = $cnt; $i <= MDL_XYZ_PRODUCT_CNT_MAX; $i++) {
                unset($this->arrParam['goods_name_' . $i]);
                unset($this->arrParam['unit_price_' . $i]);
                unset($this->arrParam['quantity_' . $i]);
            }
        }

        return $arrProducts;
    }

    /**
     * $this->arrParamに格納されている情報に合わせて、文字コード変換・サイズ調整を行う
     *
     * @param void
     * @return array $arrSendParam 変換後の送信用データ
     */
    function convParamStr () {
        $arrSendParam = array();

        // 変換前送信データをログ出力
        $this->printLog($this->arrParam);
        
        foreach ($this->arrParam as $key => $val) {
            $arrSendParam[$key] = mb_convert_encoding($val['value'], $val['encode'], "auto");

            if (strlen($val['size']) > 0) {
                // 顧客住所は50バイトずつに区切り、250バイトを超えた分は割愛
                if ($key == "bill_adr") {
                    $order_addr = substr($arrSendParam[$key], 0, 250);
                    $cnt = ceil(strlen($order_addr) / MDL_XYZ_ADDR_NAME_MAX_LEN);
                    for ($i = 1; $i <= $cnt; $i++) {
                        $arrSendParam['bill_adr_' . $i] = substr($order_addr, ($i-1)*MDL_XYZ_ADDR_NAME_MAX_LEN, MDL_XYZ_ADDR_NAME_MAX_LEN);
                    }
                    unset($arrSendParam['bill_adr']);
                } else {
                    $arrSendParam[$key] = substr($arrSendParam[$key], 0, $val['size']);
                }
            }
        }
        return $arrSendParam;
    }

    /**
     *　受注番号を取得する
     *
     */
    function getOrderId() {
        return $this->order_id;
    }

    /**
     * データを決済ステーションへ送信する
     *
     * @param string $serverUrl 送信先URL
     * @return array $arrResponse レスポンスボディ
     */
    function sendParam($serverUrl) {
        $objReq = new HTTP_Request($serverUrl);
        $arrResponse = array();

        // POSTで送信
        $objReq->setMethod('POST');

        // 送信データの文字コード変換・サイズ調整
        $arrSendParam = $this->convParamStr();

        // 送信データとして設定。
        $objReq->addPostDataArray($arrSendParam);

        // 変換後の送信データをログ出力
        $this->printLog($arrSendParam);

        // 送信
        $ret = $objReq->sendRequest();
        
        if (PEAR::isError($ret)) {
            $arrResponse['rescd'] = "エラー";
            $arrResponse['res'] = "通信ができませんでした。" . $ret->getMessage();

            // エラー内容をログ出力
            $this->printLog($ret);

            return $arrResponse;
        }

        if ($objReq->getResponseCode() !== 200) {
            $arrResponse['rescd'] = "エラー";
            $arrResponse['res'] = "通信ができませんでした。";

            // エラー内容をログ出力
            $this->printLog($objReq->getResponseCode());

            return $arrResponse;
        }

        // 決済ステーションからのレスポンスを解析
        $arrResponse = $this->parse($objReq->getResponseBody());

        // レスポンスをログ出力
        $this->printLog($arrResponse);

        return $arrResponse;
    }

    /**
     * 決済ステーションからのレスポンスを解析
     *
     * ○○○=□□□
     * から
     * array[○○○]=□□□
     * の形式にする
     *
     * @param unknown_type $response
     * @return unknown
     */
    function parse($response) {
        $arrResponse = array();

        $response = explode("\n", $response);

        foreach ($response as $key => $val) {
            $arrTemp = explode("=", $val);
            $arrResponse[$arrTemp[0]] = rtrim($arrTemp[1], "\r");
        }
        return $arrResponse;
    }

    /**
     * 送信データの文字コード変換（SJIS）を行う。
     *
     * @param array $arrSendData 変換対象
     * @return array 変換した値
     */
    function send_data_encoding($arrSendData) {
        foreach ($arrSendData as $key => $val) {
            $arrSendData[$key] = mb_convert_encoding($val, "SJIS-win", "auto");
        }
        return $arrSendData;
    }

    /**
     * モードを返す.
     *
     * @param array　$arrResponse　決済ステーションから返ってきた結果の内容
     * @return string $res_mode 結果コードに対するモード
     */
    function getMode($arrResponse) {
        $res_mode = '';
        // 決済エラー
        if ($arrResponse['rescd'] != MDL_XYZ_RES_OK && $arrResponse['rescd'] != MDL_XYZ_RES_SECURE) {
            $res_mode = 'error';

        // 3Dセキュア
        } elseif ($arrResponse['rescd'] == MDL_XYZ_RES_SECURE) {
            $res_mode = 'secure';

        // 決済OK
        } elseif ($arrResponse['rescd'] == MDL_XYZ_RES_OK) {
            $res_mode = 'complete';
        }

        return $res_mode;
    }

    /**
     * ログを出力.
     *
     * @param string $msg
     * @param mixed $data
     */
    function printLog($msg, $raw = false) {
        require_once CLASS_PATH . 'SC_Customer.php';
        $objCustomer = new SC_Customer;
        $userId = $objCustomer->getValue('customer_id');
        $path = DATA_PATH . 'logs/mdl_xyz.log';

        // パスワード等をマスクする
        if (!$raw && is_array($msg)) {
            $keys = array('card_no');
            foreach ($keys as $key) {
                if (isset($msg[$key])) {
                    $msg[$key] = ereg_replace(".", "*", $msg[$key]);
                }
            }

            $msg = print_r($msg, true);
        }

        mb_convert_variables('UTF-8', 'auto', $msg);

        GC_Utils::gfPrintLog("user=$userId: " . $msg, $path);
    }
}
?>
