<?php
require_once(MDL_XYZ_CLASS_PATH . 'SC_XYZ.php');

class SC_XYZ_Data extends SC_XYZ {

    /**
     *  コンストラクタ
     */
    function SC_XYZ_Data() {
        parent::SC_XYZ();
        $this->init();
    }

    /**
     *  初期化
     */
    function init() {
    }

    function initArrParam() {
        parent::initArrParam();
        $this->addArrParam("shiharai_kbn", 1);
    }

    /**
     * 連携データを設定する
     *
     * @param unknown_type $uniqid
     * @return array $arrParam 連携データ配列
     */
    function makeParam ($uniqid) {
        $arrParam = array();

        // 全決済共通の連携データを設定
        $arrParam = parent::makeParam($uniqid);

        // 支払区分を設定
        $arrParam['shiharai_kbn'] = "1"; //TODO 定数化する

        // データ連携に不要な項目を削除
        unset($arrParam['hakkou_kbn']);
        unset($arrParam['yuusousaki_kbn']);

        return $arrParam;
    }

    function getArrParam () {
        return $this->arrParam;
    }
}
?>
