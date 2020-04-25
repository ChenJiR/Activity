<?php

class Controller
{
    /**
     * 验证码存入redis中的namespace
     */
    const VERIFY_CODE_REDIS_NAMESPACE = "VC:";

    const MAX_TEXT_LENGHT = 500;

    /**
     * @var Request
     */
    private $_request;

    public function __construct(Request $request)
    {
        $this->_request = $request;
    }

    /**
     * index
     * @return Response
     */
    function index()
    {
        return Response::htmlReturn("view.php", ["test" => "test12123"]);
    }

    function getUserInfo()
    {
        $user = UserService::getInstance()->verifyToken($this->getToken());
        if (!$user) {
            return Response::notLogin();
        }
        return Response::ajaxSuccess("", [
            "phone" => $user->phone,
            "text" => $user->text
        ]);
    }

    /**
     * 发送验证码
     * @return Response
     */
    public function sendVerifyCode()
    {
        $phone = $this->_request->get("phone");
        if (!isset($phone) || !Helper::pregMatch($phone, "phone")) {
            return Response::ajaxError("手机号填写错误，请重试");
        }
        try {
            $now = time();

            $redis = RedisService::getInstance()->redis();
            $sms_info = $redis->get($this->generateVerifyCodeKey($phone));
            if ($sms_info && $now - json_decode($sms_info, true)["time"] <= 60) {
                return Response::ajaxError("发送频率过高，请稍后再发送短信");
            }

            $code = mt_rand(1000, 4000);
            Service::getInstance()->sendSMS($code, $phone);

            //code存入redis
            $redis_data = ["code" => $code, "time" => $now];
            $redis->set($this->generateVerifyCodeKey($phone), json_encode($redis_data), 300);

            return Response::ajaxSuccess("发送成功", $code);
        } catch (Exception $e) {
            return Response::ajaxError($e->getMessage());
        }
    }

    /**
     * 用户报名
     */
    public function signUp()
    {
        $data = $this->_request->get();
        Helper::clean_xss($data);
        if (!isset($data["phone"]) || !Helper::pregMatch($data["phone"], "phone")) {
            return Response::ajaxError("手机号填写错误，请检查后重试");
        }
        if (!$data["code"]) {
            return Response::ajaxError("请填写验证码后再试");
        }

        try {
            $sms_info = RedisService::getInstance()->redis()
                ->get($this->generateVerifyCodeKey($data["phone"]));
            if (!$sms_info) {
                return Response::ajaxError("请重新发送验证码后再试");
            }
            $sms_info = json_decode($sms_info, true);
            if ($sms_info["code"] != $data["code"]) {
                return Response::ajaxError("验证码错误，请检查后再提交");
            }

            $user = UserService::getInstance()->signUp($data["phone"]);

            return Response::ajaxSuccess("报名成功", [
                "phone" => $user->phone,
                "text" => $user->text,
                "is_lottery" => !LotteryService::getInstance()->canLottery($user),
                "token" => UserService::getInstance()->getToken($user)
            ]);
        } catch (\Exception $e) {
            return Response::ajaxError($e->getMessage());
        }
    }

    private function generateVerifyCodeKey($phone)
    {
        return self::VERIFY_CODE_REDIS_NAMESPACE . $phone;
    }

    /**
     * 提交文本
     * @return Response
     */
    public function submitText()
    {
        $text = $this->_request->post("text");
        Helper::clean_xss($text);
        if (mb_strlen($text) > self::MAX_TEXT_LENGHT) {
            return Response::ajaxError("文本长度过长，请删减后再试");
        }
        try {
            $user = UserService::getInstance()->verifyToken($this->getToken());
            if (!$user) {
                return Response::notLogin();
            }
            UserService::getInstance()->setText($user, $text);
            return Response::ajaxSuccess("提交成功", [
                "phone" => $user->phone,
                "text" => $user->text,
                "token" => UserService::getInstance()->getToken($user)
            ]);
        } catch (Exception $e) {
            return Response::ajaxError($e->getMessage());
        }
    }

    /**
     * 抽奖
     * @return Response
     */
    public function lottery()
    {
        try {
            $user = UserService::getInstance()->verifyToken($this->getToken());
            if (!$user) {
                return Response::notLogin();
            }
            if (!$user->text) {
                return Response::ajaxError("尚未提交文章，请提交文章后再进行抽奖");
            }
            if (!LotteryService::getInstance()->canLottery($user)) {
                return Response::ajaxJson(ResponseCode::CODE_ALREADY_LOTTERY, "您今日已经参加过抽奖，请明日再来");
            }

            $result = LotteryService::getInstance()->lottery($user);

            return Response::ajaxSuccess("抽奖成功", $result->name);
        } catch (Exception $e) {
            return Response::ajaxError($e->getMessage());
        }
    }

    /**
     * 获取用户token
     */
    private function getToken()
    {
        return $this->_request->getHeader()["Auth"];
    }

}
