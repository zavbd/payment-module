<?php
require_once(CLASS_PATH . "pages/LC_Page.php");
require_once(MDL_XYZ_CLASS_PATH . 'SC_Mdl_XYZ.php');

/**
 * XYZ決済モジュールの管理画面クラス.
 *
 * @package Page
 */
class LC_Page_Mdl_XYZ_Config extends LC_Page {
    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->tpl_mainpage = MDL_XYZ_TEMPLATE_PATH . '/config.tpl';
        $this->tpl_subtitle = 'XYZファイナンスサービス決済モジュール';
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $objXYZ =& SC_Mdl_XYZ::getInstance();
        $objXYZ->install();

        $objView = new SC_AdminView;

        $mode = isset($_POST['mode']) ? $_POST['mode'] : '';

        switch($mode) {
        // 登録ボタンが押された時
        case 'register':
            $this->registerMode();
            break;
        default:
            $this->defaultMode();
            break;
        }

        $objView->assignObj($this);
        $objView->display($this->tpl_mainpage);
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
     * 初回表示処理
     *
     */
    function defaultMode() {
        $objXYZ =& SC_Mdl_XYZ::getInstance();
        $subData = $objXYZ->getSubData();

        $this->initParam($subData);
        $this->arrForm = $this->objFormParam->getFormParamList();
    }

    /**
     * フォームパラメータ初期化
     *
     * @param array $arrData
     * @return SC_FormParam
     */
    function initParam($arrData = null) {
        if (is_null($arrData) == true) {
            $arrData = $_POST;
        }

        // パラメータ管理クラス
        $this->objFormParam = new SC_FormParam();

        // パラメータ情報の初期化
        $this->objFormParam->addParam("契約コード", "shop_cd", MDL_XYZ_SHOP_CD_LEN, "na", array("EXIST_CHECK", "SPTAB_CHECK", "MAX_LENGTH_CHECK"));
        $this->objFormParam->addParam("収納企業コード", "syuno_co_cd", MDL_XYZ_SYUNO_CO_CD_LEN, "na", array("EXIST_CHECK", "SPTAB_CHECK", "MAX_LENGTH_CHECK"));
        $this->objFormParam->addParam("ショップパスワード", "shop_pwd", MDL_XYZ_SHOP_PWD_LEN, "na", array("EXIST_CHECK", "SPTAB_CHECK", "MAX_LENGTH_CHECK"));

        $this->objFormParam->addParam("クレジット", "credit", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        $this->objFormParam->addParam("コンビニ（番号方式）", "conveni_number", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        $this->objFormParam->addParam("払込票（コンビニ、ゆうちょ等）", "payment_slip", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        $this->objFormParam->addParam("銀行振込", "bank_transfer", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        $this->objFormParam->addParam("ペイジー", "pay_easy", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        $this->objFormParam->addParam("電子マネー", "electronic_money", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        $this->objFormParam->addParam("ネットバンク", "netbank", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));

        if ($arrData['conveni_number'] == 1) {
            $this->objFormParam->addParam("セブンイレブン", "seven_eleven", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
            $this->objFormParam->addParam("ローソン", "lawson", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
            $this->objFormParam->addParam("セイコーマート", "seicomart", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
            $this->objFormParam->addParam("ファミリーマート", "familymart", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
            $this->objFormParam->addParam("サークルK・サンクス", "circlek_sunkus", INT_LEN, "n", array("MAX_LENGTH_CHECK", "NUM_CHECK"));
        }

        if ($arrData['payment_slip'] == 1) {
            $this->objFormParam->addParam("払込票の印刷", "payment_slip_issue", INT_LEN, "n", array("EXIST_CHECK", "MAX_LENGTH_CHECK", "NUM_CHECK"));
            if ($arrData['payment_slip_issue'] == MDL_XYZ_PAYMENT_SLIP_ISSUE_XYZ) {
                $this->objFormParam->addParam("郵送先", "payment_slip_destination", INT_LEN, "n", array("EXIST_CHECK", "MAX_LENGTH_CHECK", "NUM_CHECK"));
            }
        }

        $this->objFormParam->setParam($arrData);
        $this->objFormParam->convParam();
    }

    /**
     * 入力パラメータの検証
     *
     * @param SC_FormParam $objForm
     * @return array|null
     */
    function checkError() {
        // 入力データを渡す。
        $arrRet =  $this->objFormParam->getHashArray();
        $objErr = new SC_CheckError($arrRet);
        $objErr->arrErr = $this->objFormParam->checkError();

        if ($arrRet['credit'] != 1 && $arrRet['conveni_number'] != 1 && $arrRet['payment_slip'] != 1 && $arrRet['bank_transfer'] != 1 &&
            $arrRet['pay_easy'] != 1 && $arrRet['electronic_money'] != 1 && $arrRet['netbank'] != 1 ) {
            $objErr->arrErr['pay_type'] = "※ 利用決済が入力されていません。<br />";
        }

        if ($arrRet['conveni_number'] == 1 && $arrRet['seven_eleven'] != 1 && $arrRet['lawson'] != 1 && $arrRet['seicomart'] != 1 &&
            $arrRet['familymart'] != 1 && $arrRet['circlek_sunkus'] != 1 ) {
            $objErr->arrErr['conveni'] = "※ 対応コンビニが入力されていません。<br />";
        }

        if (extension_loaded("openssl") === false) {
            $objErr->arrErr['top'] = "※ openssl拡張モジュールをロードできません。PHPの拡張モジュールをインストールしてください。";
        }

        return $objErr->arrErr;
    }

    /**
     * 支払い方法テーブルを更新する.
     *
     * @param boolean $diffData
     */
    function updatePaymentTable($diffData) {
        $objXYZ =& SC_Mdl_XYZ::getInstance();
        $moduleCode = $objXYZ->getCode(true);

        $objQuery = new SC_Query;
        $objSess = new SC_Session;

        // 登録データ構築
        $arrPaymentInfo = array(
            "fix"            => '3',
            "module_code"    => $objXYZ->getCode(true),
            "del_flg"        => "0",
            'memo03'         => "###", // 購入フロー中、決済情報入力ページへの遷移振り分けをmemo03で判定しているため
            "creator_id"     => $objSess->member_id,
            "update_date"    => "NOW()",
        );

        // ランクの最大値を取得する
        $max_rank = $objQuery->getOne("SELECT max(rank) FROM dtb_payment");
        $arrPaymentInfo['rank'] = $max_rank + 1;

        $arrPaymentInfo = array_merge($arrPaymentInfo, $diffData);
        $count = $objQuery->count('dtb_payment', 'module_code = ? AND memo01 = ?', array($moduleCode, $diffData['memo01']));
        if($count) {
            $objQuery->update("dtb_payment", $arrPaymentInfo, "module_code = ? AND memo01 = ?", array($moduleCode, $diffData['memo01']));
        } else {
            $objQuery->insert("dtb_payment", $arrPaymentInfo);
        }
    }

    /**
     * 支払方法の削除
     *
     */
    function deletePaymentType($paymentType) {
        $objXYZ =& SC_Mdl_XYZ::getInstance();
        $moduleCode = $objXYZ->getCode(true);

        $objQuery = new SC_Query;
        $objQuery->update(
            "dtb_payment", array('del_flg' => '1'),
            "module_code = ? AND memo01 = ?", array($moduleCode, $paymentType)
        );
    }

    /**
     * 全テーブルリストを取得する
     *
     */
    function getTableList(){
        $objQuery = new SC_Query();

        if(DB_TYPE == "pgsql"){
            $sql = "SELECT tablename FROM pg_tables WHERE tableowner = ? ORDER BY tablename ; ";
            $arrRet = $objQuery->getAll($sql, array(DB_USER));
            $arrRet = SC_Utils_Ex::sfSwapArray($arrRet);
            $arrRet = $arrRet['tablename'];
        }else if(DB_TYPE == "mysql"){
            $sql = "SHOW TABLES;";
            $arrRet = $objQuery->getAll($sql);
            $arrRet = SC_Utils_Ex::sfSwapArray($arrRet);

            // キーを取得
            $arrKey = array_keys($arrRet);

            $arrRet = $arrRet[$arrKey[0]];
        }
        return $arrRet;
    }

    /**
     * 登録ボタン押下時の処理
     *
     */
    function registerMode() {
        $objQuery = new SC_Query();

        // パラメータの初期化
        $this->initParam();

        // エラーチェック
        $this->arrErr = $this->checkError();

        if (count($this->arrErr) == 0) {
            $arrForm = $this->objFormParam->getHashArray();
            $objXYZ =& SC_Mdl_XYZ::getInstance();

            ////////////////////////////////////////////////////////////
            // ファイルのコピー                                       //
            ////////////////////////////////////////////////////////////
            $arrFailedFile = $objXYZ->updateFile();
            if (count($arrFailedFile) > 0) {
                $this->arrForm = $this->objFormParam->getFormParamList();
                foreach($arrFailedFile as $file) {
                    $alert = $file . 'に書き込み権限を与えてください。';
                    $this->tpl_onload .= "alert('" . $alert . "');";
                }
                return;
            }

            ////////////////////////////////////////////////////////////
            // 入力内容を、dtb_module.sub_dataへ登録                  //
            ////////////////////////////////////////////////////////////
            $objXYZ->registerSubData($arrForm);

            ////////////////////////////////////////////////////////////
            // dtb_mdl_xyz_orderテーブルの存在チェックを行い         //
            // 存在しなければテーブルを作成する                       //
            ////////////////////////////////////////////////////////////
            // テーブルの存在チェック
            $arrTableList = $this->getTableList();
            // 存在していなければ作成
            if(!in_array("dtb_mdl_xyz_order", $arrTableList)){
                $cre_sql = "create table dtb_mdl_xyz_order (
                    order_id INT4,
                    credit_status INT2,
                    update_date timestamp
                );
            ";
                $objQuery->query($cre_sql);
            }

            ////////////////////////////////////////////////////////////
            // dtb_paymentへ支払方法を設定する。                      //
            // チェックボックスで選択されなかった支払方法は論理削除。 //
            ////////////////////////////////////////////////////////////
            // ネットバンク
            if (isset($arrForm['netbank']) && $arrForm['netbank'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'netbank.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_NETBUNK_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_NETBUNK_BILL_METHOD, "payment_method" => MDL_XYZ_NETBUNK_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_NETBUNK_BILL_METHOD);
            }
            // 電子マネー
            if (isset($arrForm['electronic_money']) && $arrForm['electronic_money'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'e_money.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_ELECTRONIC_MONEY_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_ELECTRONIC_MONEY_BILL_METHOD, "payment_method" => MDL_XYZ_ELECTRONIC_MONEY_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_ELECTRONIC_MONEY_BILL_METHOD);
            }
            // ペイジー
            if (isset($arrForm['pay_easy']) && $arrForm['pay_easy'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'payeasy.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_PAYEASY_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_PAYEASY_BILL_METHOD, "payment_method" => MDL_XYZ_PAYEASY_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_PAYEASY_BILL_METHOD);
            }
            // 銀行振込
            if (isset($arrForm['bank_transfer']) && $arrForm['bank_transfer'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'bank_transfer.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_BANK_TRANSFER_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_BANK_TRANSFER_BILL_METHOD, "payment_method" => MDL_XYZ_BANK_TRANSFER_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_BANK_TRANSFER_BILL_METHOD);
            }
            // 払込票（コンビニ、ゆうちょ等）
            if (isset($arrForm['payment_slip']) && $arrForm['payment_slip'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'payment_slip.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_PAYMENT_SLIP_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_PAYMENT_SLIP_BILL_METHOD, "payment_method" => MDL_XYZ_PAYMENT_SLIP_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_PAYMENT_SLIP_BILL_METHOD);
            }
            // コンビニ（番号方式）
            if (isset($arrForm['conveni_number']) && $arrForm['conveni_number'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'conveni.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_CONVENI_NUMBER_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_CONVENI_NUMBER_BILL_METHOD, "payment_method" => MDL_XYZ_CONVENI_NUMBER_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_CONVENI_NUMBER_BILL_METHOD);
            }
            // クレジット決済
            if (isset($arrForm['credit']) && $arrForm['credit'] == '1') {
                $this->updatePaymentTable(
                    array('module_path' => MDL_XYZ_PATH . 'credit.php', 'rule' => 1, 'upper_rule' => MDL_XYZ_CREDIT_UPPER_RULE_MAX, 'memo01' => MDL_XYZ_CREDIT_BILL_METHOD, "payment_method" => MDL_XYZ_CREDIT_PAY_TYPE)
                );
            } else {
                $this->deletePaymentType(MDL_XYZ_CREDIT_BILL_METHOD);
            }


            $this->tpl_onload = "alert('登録完了しました。". '\n基本情報＞支払方法設定より詳細設定をしてください。' . "'); window.close();";
        }

        $this->arrForm = $this->objFormParam->getFormParamList();
    }
}


?>
