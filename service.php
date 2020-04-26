<?php

class Service
{
    protected static $instance;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!(static::$instance instanceof static)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * 发送短信（验证码）
     * @param string $text
     * @param string $phone
     * @return bool
     */
    public function sendSMS($text, $phone)
    {
        $this->log("发送短信", ["text" => $text, "phone" => $phone]);
        return true;
    }

    /**
     * 日志
     * @param $msg
     * @param null $data
     * @return bool
     */
    protected function log($msg, $data = null)
    {
//        $backtrace = debug_backtrace();
//        $log = [
//            'position' => $backtrace[1]['class'] . '->' . $backtrace[1]['function'],
//            'message' => $msg,
//            'data' => is_array($data) ? json_encode($data) : $data
//        ];
        return true;
    }

    /**
     * @param $input
     * @return string
     */
    protected function base64UrlEncode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    protected function base64UrlDecode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $addlen = 4 - $remainder;
            $input .= str_repeat('=', $addlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

}

class LotteryService extends Service
{

    public $error;

    //抽奖锁，保证抽奖流程为单一队列
    const LOCK_KEY = "lottery_lock";

    //今日所有抽中商品缓存
    const TODAY_WIN_CACHE_KEY = "today_win_prize:";
    //每种商品被抽中次数缓存
    const PRIZE_WIN_CACHE_KEY = "prize_win_count:";

    /**
     * 抽奖
     * @param User $user
     * @return bool|Prize
     */
    public function lottery(User $user)
    {
        $prize_json = file_get_contents("prize.json");
        $prize_list = [];
        foreach (json_decode($prize_json, true) as $item) {
            $prize_list[] = new Prize($item);
        }

        $redis = RedisService::getInstance()->redis();
        while (false === $redis->setnx(self::LOCK_KEY, 1)) {
            usleep(mt_rand(1000, 15000));
        }
        try {
            $redis->expire(self::LOCK_KEY, 5);

            if (!$this->canLottery($user)) {
                throw new Exception("今日已参加过抽奖，请明天再来");
            }
            $prize = $this->lotteryCore($user, $prize_list);

            $this->_afterLottery($user, $prize);

            return $prize;
        } catch (\Exception $e) {
            $redis->del(self::LOCK_KEY);
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * 抽奖算法
     * @param User $user
     * @param Prize[] $prize_list
     * @return Prize
     */
    private function lotteryCore(User $user, array $prize_list)
    {
        $min = $max = $init = 0;
        $prize_scope = [];
        foreach ($prize_list as $item) {
            if ($item->enable($user)) {
                $max = $min + $item->probability;
                $prize_scope[] = ["min" => $min, "max" => $max, "prize" => $item];
                $min = $max;
            }
        }
        $win = mt_rand($init + 1, $max);
        foreach ($prize_scope as $item) {
            if ($win > $item["min"] && $win <= $item["max"]) {
                return $item["prize"];
            }
        }
    }

    /**
     * 检查用户是否已抽过奖
     * @param User $user
     * @return bool
     */
    public function canLottery(User $user)
    {
        return Lottery::countUserLotteryToday($user->phone) == 0;
    }

    /**
     * 抽奖后方法 主要存入抽奖记录及中奖记录
     * @param User $user
     * @param Prize $prize
     */
    private function _afterLottery(User $user, Prize $prize)
    {
        RedisService::getInstance()->redis()->multi();
        $lottery = new Lottery();
        $lottery->phone = $user->phone;
        $lottery->prize_code = $prize->code;
        $lottery->save();
        $this->_savePrizeWinCount($prize);
        $this->_saveTodayWinPrize($prize);
        RedisService::getInstance()->redis()->exec();
    }

    /**
     * 保存当前奖品当日中奖缓存
     * @param Prize $prize
     */
    private function _saveTodayWinPrize(Prize $prize)
    {
        $key = self::TODAY_WIN_CACHE_KEY . $prize->code;
        $redis = RedisService::getInstance()->redis();
        $redis->incr($key);
        $redis->expireAt($key, strtotime(date("Y-m-d", strtotime("+1 day"))));
    }

    /**
     * 获取当日该奖品中将次数
     * @param Prize $prize
     * @return int
     */
    public function getTodayWinPrize(Prize $prize)
    {
        $key = self::TODAY_WIN_CACHE_KEY . $prize->code;
        return RedisService::getInstance()->redis()->get($key) ?: 0;
    }

    /**
     * 保存奖品中奖次数
     * @param Prize $prize
     */
    private function _savePrizeWinCount(Prize $prize)
    {
        $key = self::PRIZE_WIN_CACHE_KEY . $prize->code;
        $redis = RedisService::getInstance()->redis();
        $redis->incr($key);
    }

    /**
     * 获取奖品中奖次数
     * @param Prize $prize
     * @return int
     */
    public function getPrizeWinCount(Prize $prize)
    {
        $key = self::PRIZE_WIN_CACHE_KEY . $prize->code;
        $redis = RedisService::getInstance()->redis();
        $res = $redis->get($key);
        if (false === $res) {
            $res = Lottery::countPrizeNum($prize->code);
            $redis->set($key, $res);
        }
        return $res;
    }

}

class UserService extends Service
{
    /**
     * @param string $phone
     * @return User
     * @throws Exception
     */
    public function signUp($phone)
    {
        $user = User::select($phone);
        if (!$user) {
            $user = new User();
            $user->phone = $phone;
            if (!$user->save()) {
                $this->log("未知错误", $phone);
                throw new Exception("未知错误，请稍后重试");
            }
        }
        return $user;
    }

    /**
     * 上传文本
     * @param User $user
     * @param $text
     * @return User
     * @throws Exception
     */
    public function setText(User $user, $text)
    {
        $user->text = $text;
        if ($user->save()) {
            return $user;
        } else {
            $this->log("未知错误", $user);
            throw new Exception("未知错误，请稍后重试");
        }
    }

    /**
     * 使用jwt进行用户信息加密token
     * 这里不再实现jwt加密，直接使用 base64UrlEncode 实现简单加密
     * @param User $user
     * @return string
     */
    public function getToken(User $user)
    {
        return self::base64UrlEncode($user->phone);
    }

    /**
     * 使用jwt进行用户鉴权
     * 这里不再实现jwt解密及鉴权，直接使用 base64UrlDecode 实现简单解密
     * @param string $token
     * @return User | false
     */
    public function verifyToken($token)
    {
        if (!$token) return false;
        try {
            return $this->signUp(self::base64UrlDecode($token));
        } catch (Exception $e) {
            return false;
        }
    }
}

class RedisService extends Service
{
    private $redis;

    public function redis()
    {
        if ($this->redis === null) {
            $this->redis = new Redis();
        }
        if (!$this->redis->isConnected()) {
            $config = require("config.php");
            $this->redis->pconnect($config["redis"]["hostname"], $config["redis"]["port"]);
            $config["redis"]["password"] && $this->redis->auth($config["redis"]["password"]);
        }
        return $this->redis;
    }

    public function countKeys($pattern, $count = 1000)
    {
        if (!$pattern) {
            return 0;
        }
        $redis = $this->redis();
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $res = 0;
        $iterator = null;
        while (false !== ($keys = $redis->scan($iterator, $pattern, $count))) {
            $res += count($keys);
        }
        return $res;
    }

    /**
     * 迭代获取key
     * @param $pattern
     * @param int $count
     * @return Generator
     */
    public function keys($pattern, $count = 1000)
    {
        if (!$pattern) {
            return;
        }
        $redis = $this->redis();
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $iterator = null;
        while (false !== ($keys = $redis->scan($iterator, $pattern, $count))) {
            foreach ($keys as $key) {
                yield $key;
            }
        }
    }

    /**
     * 迭代获取值
     * @param $pattern
     * @param int $count
     * @return Generator
     */
    public function iterratorKeys($pattern, $count = 1000)
    {
        if (!$pattern) {
            return;
        }
        $redis = $this->redis();
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $iterator = null;
        while (false !== ($keys = $redis->scan($iterator, $pattern, $count))) {
            foreach ($keys as $key) {
                yield $redis->get($key);
            }
        }
    }
}