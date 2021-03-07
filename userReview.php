<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
use Bitrix\Main\Loader;
use Site\BPGoogleRecaptcha;

$rew = new UserReview();
$rew->execute();

class UserReview
{
    protected $errors = array();
    private $requestFieldsArray;
    private $requiredFields = array("NAME", "USER_EMAIL", "COMMENT", "RATING" );
    private $captchaSecret;
    private $captchaPublic;
    const REV_IBLOCK_ID = 19;
    const EVENT_ID = "USER_SEND_REVIEW";
    const DEFAULT_CHARACTERISTIC = "Не указаны";
    const SUCCESS_MESSAGE = "Ваш отзыв будет опубликован после проверки модератором";




    public function __construct()
    {
        $this->requestFieldsArray = \Bitrix\Main\Context::getCurrent()->getRequest()->getPostList()->toArray();
        $this->captchaSecret = COption::GetOptionString("main", "recaptcha_secret");
        $this->captchaPublic = COption::GetOptionString("main", "recaptcha_public");

    }


    private function clearData(array &$fields)
    {
        foreach ($fields as &$field) {
            $field = htmlspecialchars(trim($field));
        }
    }


    private function checkRequestFields(array $fields)
    {
        $this->clearData($this->requestFieldsArray);

            foreach ($fields as $name => $field) {
                if (in_array($name, $this->requiredFields)
                    && $field == "") {
                    $this->errors[0] = "Заполните обязательные поля";
                }
            }
            if (!empty($fields["USER_EMAIL"]) && filter_var($fields["USER_EMAIL"], FILTER_VALIDATE_EMAIL) === false) {
                array_push($this->errors, "Вы ввели некорректный email");
            }
    }

    private function includeModules()
    {
        Loader::includeModule('iblock');
    }

    private function createIBElement()
    {
        $el = new CIBlockElement;
        global $USER;

        $PROP = array();
        $PROP["USER"] = $USER->GetID();
        $PROP["NAME"] = $this->requestFieldsArray["NAME"];
        $PROP["EMAIL"] = $this->requestFieldsArray["USER_EMAIL"];
        $PROP["COMMENT"] = $this->requestFieldsArray["COMMENT"];
        $PROP["POSITIV"] = $this->requestFieldsArray["DIGNITY"];
        $PROP["NEGATIV"] = $this->requestFieldsArray["LIMITATION"];
        $PROP["RATING"] = $this->requestFieldsArray["RATING"];
        $PROP["PRODUCT_ID"] = $this->requestFieldsArray["PRODUCT_ID"];

        $arLoadProductArray = Array(
            "IBLOCK_SECTION_ID" => false,          // element lies in the root section
            "IBLOCK_ID"      => self::REV_IBLOCK_ID,
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => "Отзыв пользователя ".$this->requestFieldsArray["NAME"],
            "ACTIVE"         => "N",
        );

        return $el->Add($arLoadProductArray);

    }

    private function sendReview()
    {
        $revID = $this->createIBElement();
        if($revID > 0){
            $arEventFields = Array(
               "NAME" =>  $this->requestFieldsArray["NAME"],
               "USER_EMAIL" => $this->requestFieldsArray["USER_EMAIL"],
               "DATE_TIME" => date("m.d.y H:i:s"),
               "RATING" => $this->requestFieldsArray["RATING"],
               "COMMENT" => $this->requestFieldsArray["COMMENT"],
                "LINK" => $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=".self::REV_IBLOCK_ID."&type=data_from_users&lang=ru&ID=".$revID."&find_section_section=-1&WF=Y",
                "PRODUCT_NAME" => $this->requestFieldsArray["PRODUCT_NAME"]
            );

            if(!empty($this->requestFieldsArray["LIMITATION"])){
                $arEventFields["LIMITATIONS"] =  $this->requestFieldsArray["LIMITATION"];
            } else {
                $arEventFields["LIMITATIONS"] = self::DEFAULT_CHARACTERISTIC;
            }

            if(!empty($this->requestFieldsArray["LIMITATION"])){
                $arEventFields["DIGNITY"] =  $this->requestFieldsArray["DIGNITY"];
            } else {
                $arEventFields["DIGNITY"] = self::DEFAULT_CHARACTERISTIC;
            }

            $eventID = CEvent::Send(self::EVENT_ID, "s1", $arEventFields);
            CEvent::CheckEvents();
            if($eventID){
                echo json_encode(["status" => "success", "data" => self::SUCCESS_MESSAGE]);
            }
        } else {
            array_push($this->errors, "Ошибка добавления отзыва. Обратитесь в техподдержку");
            echo json_encode(["status" => "error", "data" => $this->errors]);
        }
    }

    public function execute()
    {
        try {
                $this->checkRequestFields($this->requestFieldsArray);
                if (!empty($this->errors)) {
                    echo json_encode(["status" => "error", "data" => $this->errors]);
                } else {
                    $captcha = new BPGoogleRecaptcha();
                    if ($captcha->checkUser($this->captchaSecret, $this->requestFieldsArray["g-recaptcha-response"])) {
                        $this->includeModules();
                        $this->sendReview();
                    } else {
                        array_push($this->errors, "Вы не прошли идентификацию по капче");
                        echo json_encode(["status" => "error", "data" => $this->errors]);
                    }

                }

                die();


        } catch (SystemException $e) {
            ShowError($e->getMessage());
        }
    }

}