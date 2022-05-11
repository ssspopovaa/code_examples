<?php

namespace App\Helpers;

/**
 * Class PhoneCallLogsHelper
 * @package App\Helpers
 */
class PhoneCallLogsHelper
{
    const PHONE_CALL_LOG_FOLDER = 'phoneCallLogs';
    const COUNTRY_DIALING_AMERICA_CODE = '+1';
    const PHONE_CALL_TYPES_FOLDER_ARRAY = ['vmail/new', 'vmail/save', 'trash'];

    /**
     * @return bool|string
     */
    public static function login()
    {
        $token = self::getAuthToken();

        return json_decode($token);
    }

    /**
     * @return bool|string
     */
    public static function getAuthToken()
    {
        $clientId = env('NETSAPIENS_CLIENT_ID');
        $clientSecret = env('NETSAPIENS_CLIENT_SECRET');
        $clientUser = env('NETSAPIENS_USER');
        $clientPass = env('NETSAPIENS_PASS');
        $clientDomain = env('NETSAPIENS_DOMAIN');

        $url = "https://voip.infiniwiz.com/ns-api/oauth2/token?grant_type=password&client_id=$clientId&client_secret=$clientSecret&username=$clientUser&password=$clientPass";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $token = curl_exec($ch);
        curl_close($ch);

        return $token;
    }

    /**
     * @param string $url
     * @param array $header
     * @param array $vars
     *
     * @return string
     */
    public static function curl_post($url, array $header, array $vars)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            throw new \Exception($err);
        } else {
            return $response;
        }
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $token
     * @return mixed
     * @throws \Exception
     */
    public static function getPhoneCallLog($startDate, $endDate, $token)
    {
        $url = 'https://voip.infiniwiz.com/ns-api/?format=json&object=cdr2&action=read';

        $clientDomain = env('NETSAPIENS_DOMAIN');

        $header[] = "Authorization: Bearer " . $token;

        $params = [
            'domain' => $clientDomain,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'raw' => 'yes',
        ];

        return json_decode(self::curl_post($url, $header, $params));
    }

    /**
     * @param $token
     * @param $origCallId
     * @param $termCallId
     * @return string
     * @throws \Exception
     */
    public static function getRecord($token, $origCallId, $termCallId)
    {
        $clientDomain = env('NETSAPIENS_DOMAIN');

        $url = "voip.infiniwiz.com/ns-api/?format=json&object=recording&action=read";

        $header[] = "Authorization: Bearer " . $token;

        $params = [
            'domain' => $clientDomain,
            'orig_callid' => $origCallId,
            'term_callid' => $termCallId,
        ];

        return json_decode(self::curl_post($url, $header, $params));
    }

    /**
     * @param $token
     * @param $index
     * @param string $user
     * @return mixed
     * @throws \Exception
     */
    public static function getVoiceMailRecord($token, $index, $user)
    {
        $folderTypes = PhoneCallLogsHelper::PHONE_CALL_TYPES_FOLDER_ARRAY;

        $clientDomain = env('NETSAPIENS_DOMAIN');
        $url = "voip.infiniwiz.com/ns-api/?format=json&object=audio&action=read";
        $header[] = "Authorization: Bearer " . $token;

        foreach ($folderTypes as $type) {
            $params = [
                'domain' => $clientDomain,
                'type' => $type,
                'user' => $user,
                'index' => $index,
            ];

            $recordLog = json_decode(self::curl_post($url, $header, $params));

            if (isset($recordLog->$index) && isset($recordLog->$index->remotepath)) {
                return $recordLog;
                break;
            }
        }

        return [];
    }
}
