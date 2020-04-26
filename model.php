<?php


abstract class Model
{
    protected static function dbNamespace()
    {
    }

    abstract protected function PK();

    public function save()
    {
        if (!$this->{static::PK()}) {
            return false;
        }
        $key = static::dbNamespace() . $this->{static::PK()};
        return RedisService::getInstance()->redis()->set($key, serialize($this));
    }

    public static function select($pk)
    {
        $result = RedisService::getInstance()->redis()->get(static::dbNamespace() . $pk);
        return $result ? unserialize($result) : null;
    }

}

class User extends Model
{

    /**
     * 用户手机号
     * @var string
     */
    public $phone;

    /**
     * 征文内容
     * @var string
     */
    public $text;

    /**
     * 注册时间
     * @var string
     */
    public $sign_up_time;

    protected static function dbNamespace()
    {
        return "u:";
    }

    protected function PK()
    {
        return "phone";
    }

    public function save()
    {
        if (!$this->{static::PK()}) {
            return false;
        }
        $key = static::dbNamespace() . $this->{static::PK()};
        $redis = RedisService::getInstance()->redis();
        if (!$redis->get($key)) {
            $this->sign_up_time = Helper::NowTime();
        }
        return RedisService::getInstance()->redis()->set($key, serialize($this));
    }
}

class Prize
{

    /**
     * 奖品code码
     * @var string
     */
    public $code;

    /**
     * 奖品名称
     * @var string
     */
    public $name;

    /**
     * 中将概率 (以百分比计算，所以为整数)
     * @var int
     */
    public $probability;

    /**
     * 总额，-1为不限额
     * @var int
     */
    public $total = -1;

    /**
     * 每日限额，-1为不限额
     * @var int
     */
    public $day_limit = -1;

    /**
     * 每个用户限额，-1为不限额
     * @var int
     */
    public $user_limit = -1;

    /**
     * 是否可用
     * @var bool
     */
    private $enable;

    /**
     * Prize constructor.
     * @param array $prize_info
     */
    public function __construct(array $prize_info)
    {
        foreach ($prize_info as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * 该奖品是否可用
     * @param User $user
     * @return bool
     */
    public function enable(User $user)
    {
        //是否为每日限量并超过限量
        if (-1 !== $this->day_limit && LotteryService::getInstance()->getTodayWinPrize($this) >= $this->day_limit) {
            return false;
        }
        //是否为每人限量并超过限量
        if (-1 !== $this->user_limit && Lottery::countUserWinPrize($user->phone, $this->code) >= $this->user_limit) {
            return false;
        }
        //是否为总数限量并超过限量
        if (-1 !== $this->total && LotteryService::getInstance()->getPrizeWinCount($this) >= $this->total) {
            return false;
        }
        return true;
    }

}

class Lottery extends Model
{

    protected static function dbNamespace()
    {
        return "lottery:";
    }

    protected function PK()
    {
        return "phone";
    }

    /**
     * 中奖用户手机号
     * @var string
     */
    public $phone;

    /**
     * 奖品code
     * @var string
     */
    public $prize_code;

    /**
     * @var string
     */
    public $lottery_time;

    /**
     * 保存抽奖记录
     * @return bool
     */
    public function save()
    {
        if (!$this->phone || !$this->prize_code) {
            return false;
        }
        $key = static::dbNamespace() . "$this->phone|$this->prize_code|" . date('Ymd');
        $this->lottery_time = Helper::NowTime();
        return RedisService::getInstance()->redis()->set($key, serialize($this));
    }

    /**
     * 获取用户中某个奖的个数
     * @param $phone
     * @param $prize_code
     * @return int
     */
    public static function countUserWinPrize($phone, $prize_code)
    {
        $search_key = static::dbNamespace() . "$phone|$prize_code|*";
        return RedisService::getInstance()->countKeys($search_key);
    }

    /**
     * 用户今日抽奖次数
     * @param $phone
     * @return bool
     */
    public static function countUserLotteryToday($phone)
    {
        $search_key = static::dbNamespace() . "$phone|*|" . date('Ymd');
        return RedisService::getInstance()->countKeys($search_key);
    }

    /**
     * 获取某个奖品已中的数量
     * @param $prize_code
     * @return int
     */
    public static function countPrizeNum($prize_code)
    {
        $search_key = static::dbNamespace() . "*|$prize_code|*";
        return RedisService::getInstance()->countKeys($search_key);
    }

}