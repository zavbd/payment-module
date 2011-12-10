<?php
/**
 * XYZ決済モジュール
 *
 */

/*
 * モジュール設定情報
 */
class SC_Mdl_XYZ {
    /** サブデータを保持する変数 */
    var $subData = null;

    /** モジュール情報 */
    var $moduleInfo = array(
        'moduleName'  => 'XYZ決済',
        'moduleCode'  => 'mdl_xyz'
    );

    /**
     * テーブル拡張設定.拡張したいテーブル情報を配列で記述する.
     * $updateTable = array(
     *     array(
     *       'name' => 'テーブル名',
     *       'cols' => array(
     *          array('name' => 'カラム名', 'type' => '型名'),
     *          array('name' => 'カラム名', 'type' => '型名'),
     *       ),
     *     ),
     *     array(
     *       ...
     *     ),
     *     array(
     *       ...
     *     ),
     * );
     */
    var $updateTable = array(
        // dtb_paymentの更新
        array(
            'name' => 'dtb_payment',
            'cols'  => array(
                array('name' => 'module_code', 'type' => 'text'),
            ),
        ),
    );

    /**
     * コピーファイル
     *
     * @var unknown_type
     */
    var $updateFile = array();



    function SC_Mdl_XYZ() {

        $this->updateFile = array(
// 特に上書きの必要はない
//            array(
//                "src" => "load_payment_module_pc.php",
//                "dst" => HTML_PATH . 'shopping/load_payment_module.php'
//            ),
//            array(
//                "src" => "load_payment_module_mobile.php",
//                "dst" => HTML_PATH . 'mobile/shopping/load_payment_module.php'
//            ),
            array(
                "src" => "credit.php",
                "dst" => HTML_PATH . 'admin/order/credit.php'
            ),
            array(
                "src" => "subnavi.tpl",
                "dst" => TEMPLATE_DIR . 'admin/order/subnavi.tpl'
            ),
            array(
                "src" => "order_recv.php",
                "dst" => HTML_PATH . 'xyz/order_recv.php'
            ),
            array(
                "src" => "payment_recv.php",
                "dst" => HTML_PATH . 'xyz/payment_recv.php'
            ),
            array(
                "src" => "overtime_recv.php",
                "dst" => HTML_PATH . 'xyz/overtime_recv.php'
            ),
            array(
                "src" => "complete.php",
                "dst" => HTML_PATH . 'xyz/complete.php'
            ),
            array(
                "src" => "credit_secure.php",
                "dst" => HTML_PATH . 'xyz/credit_secure.php'
            ),
            array(
                "src" => "complete_mobile.php",
                "dst" => HTML_PATH . 'mobile/xyz/complete.php'
            ),
                                  );
    }

    /**
     * SC_Mdl_XYZのインスタンスを取得する
     *
     * @return SC_Mdl_XYZ
     */
    function &getInstance() {
        static $_objSC_Mdl_XYZ;
        if (empty($_objSC_Mdl_XYZ)) {
            $_objSC_Mdl_XYZ = new SC_Mdl_XYZ();
        }

        $_objSC_Mdl_XYZ->init($this->updateFile);
        return $_objSC_Mdl_XYZ;
    }

    /**
     * 初期化処理.
     */
    function init() {
        foreach ($this->moduleInfo as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * モジュール表示用名称を取得する
     *
     * @return string
     */
    function getName() {
        return $this->moduleName;
    }

    /**
     * 支払い方法名(決済モジュールの場合のみ)
     *
     * @return string
     */
    function getPaymentName() {
        return $this->paymentName;
    }

    /**
     * モジュールコードを取得する
     *
     * @param boolean $toLower trueの場合は小文字へ変換する.デフォルトはfalse.
     * @return string
     */
    function getCode($toLower = false) {
        $moduleCode = $this->moduleCode;
        return $toLower ? strtolower($moduleCode) : $moduleCode;
    }

    /**
     * サブデータを取得する.
     *
     * @return mixed|null
     */
    function getSubData() {
        if (isset($this->subData)) return $this->subData;

        $moduleCode = $this->getCode(true);
        $objQuery = new SC_Query;
        $ret = $objQuery->get(
            'dtb_module', 'sub_data', 'module_code = ?', array($moduleCode)
        );

        if (isset($ret)) {
            $this->subData = unserialize($ret);
            return $this->subData;
        }
        return null;
    }

    /**
     * サブデータをDBへ登録する
     *
     * @param mixed $data
     */
    function registerSubData($data) {
        $subData = $data;

        $arrUpdate = array('sub_data' => serialize($subData));
        $objQuery = new SC_Query;
        $objQuery->update('dtb_module', $arrUpdate, 'module_code = ?', array($this->getCode(true)));

        $this->subData = $subData;
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
        $path = DATA_PATH . 'logs/' . $this->getCode(true) . '.log';

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

    /**
     * デバッグログを出力.
     *
     * @param string $msg
     * @param mixed $data
     */
    function printDebugLog($msg, $data = null) {
        if (DEBUG_MODE === true) {
            $this->printLog($msg, $data);
        }
    }

    /**
     * インストール処理
     *
     * @param boolean $force true時、上書き登録を行う
     */
    function install($force = false) {
        // カラムの更新
        $this->updateTable();
    }

    /**
     * カラムの更新を行う.
     *
     */
    function updateTable() {
        $objDB = new SC_Helper_DB_Ex();
        foreach ($this->updateTable as $table) {
            foreach($table['cols'] as $col) {
                $objDB->sfColumnExists(
                    $table['name'], $col['name'], $col['type'], "", $add = true
                );
            }
        }
    }

    /**
     * ファイルをコピーする
     *
     * @return boolean
     */
    function updateFile() {
        return $this->copyFiles($this->updateFile);
    }

    // 再帰的にパスを作成する。(php4にはrecursiveがない)
    // http://www.php.net/manual/ja/function.mkdir.php
    function mkdirp($pathname, $mode) {
        is_dir(dirname($pathname)) || $this->mkdirp(dirname($pathname), $mode);
        return is_dir($pathname) || mkdir($pathname, $mode);
    }

    function copyFiles($files) {
        $failedCopyFile = array();

        foreach($files as $file) {
            $dst_file = $file['dst'];
            $src_file = MDL_XYZ_PATH . 'copy/' . $file['src'];

            // ファイルがない、またはファイルはあるが異なる場合
            if(!file_exists($dst_file) || sha1_file($src_file) != sha1_file($dst_file)) {
                if(is_writable($dst_file) || is_writable(dirname($dst_file)) || $this->mkdirp(dirname($dst_file), 0777)) {
                    // backupを作成
                    if (file_exists($dst_file)) {
                        copy($dst_file, $dst_file . '.mdl_xyz' . date(".Ymd"));
                    }

                    if (!copy($src_file, $dst_file)) {
                        $failedCopyFile[] = $dst_file;
                    }
                } else {
                    $failedCopyFile[] = $dst_file;
                }
            }
        }

        return $failedCopyFile;
    }
}
?>
