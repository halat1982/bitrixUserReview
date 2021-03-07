<?php

namespace Site;

class BitrixGoogleRecaptcha
{

    const URL = "https://www.google.com/recaptcha/api/siteverify";


    public function checkUser($secret, $token)
    {
        $postData = array(
            "secret" => $secret,
            "response" => $token
        );
        $result = $this->curlExec(self::URL, $postData);

        return $this->checkResponse($result);
    }

    private function checkResponse($result)
    {
        if ($result->success === true) {
            return true;
        }

        return false;
    }

    private function curlExec($url, $postData)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($ch);
        if ($response) {
            $result = json_decode($response);
        }
        if ($error = curl_error($ch)) {
            AddMessage2Log(date(DATE_RFC822) . "<br>" . $error . " CURL ERROR");
            die('curl error');
        }
        if (!empty($result->message)) {
            AddMessage2Log(date(DATE_RFC822) . "<br>" . $error . "GOOGLE ERROR");
            die('Google error');
        }

        curl_close($ch);
        return $result;
    }


}