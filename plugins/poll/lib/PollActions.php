<?php
/**
 * Class PollActions
 *
 * Package and location is not indicated
 * Should not include any kaltura dependency in this class - to enable it to run in cache only mode
 */
class PollActions
{

	const ID_SEPARATOR_CHAR = '-';
	const ANSWER_SEPARATOR_CHAR = ';';
	const ID_NUM_ELEMENTS = 3;

	/**
	 * @var PollCacheHandler
	 */
	private static $pollsCacheHandler = null;
	/**
	 * @var array of const strings representing poll types
	 */
	private static $pollTypes = null;
	/**
	 * @var string
	 */
	private static $kalturaSecret = null;

	/* Configuration */
	/**
	 *
	 * @throws Exception
	 */
	private static function init()
	{
		self::$kalturaSecret = kConf::get("polls_secret");
		if (!self::$kalturaSecret)
			throw new Exception("Could not find polls_secret in the configuration");
		self::$pollsCacheHandler = new PollCacheHandler();
		self::$pollTypes = array(
			'SINGLE_ANONYMOUS',
			'SINGLE_RESTRICT',
			'MULTI_ANONYMOUS',
			'MULTI_RESTRICT');
	}

	/* Poll Id Action */
	public static function generatePollId($type = 'SINGLE_ANONYMOUS')
	{
		self::init();
		if (!self::isValidPollType($type))
			throw new Exception("Poll type provided is invalid");
		$randKey = rand();
		$hash = hash_hmac('SHA256', self::$kalturaSecret, $randKey);
		return $type.self::ID_SEPARATOR_CHAR.$hash.self::ID_SEPARATOR_CHAR.$randKey;
	}

	private static function  isValidPollType($type)
	{
		return in_array($type, self::$pollTypes);
	}

	private static function isValidPollIdStructure($id)
	{
		self::init();
		$idElements = explode(self::ID_SEPARATOR_CHAR, $id);
		if (count($idElements) === self::ID_NUM_ELEMENTS ) {
			$pollType = $idElements[0];
			$hash = $idElements[1];
			$key = $idElements[2];
			$simulatedHash = hash_hmac('SHA256', self::$kalturaSecret, $key);
			$isHashOk = strcmp($hash, $simulatedHash) === 0;
			$validPollType = self::isValidPollType($pollType);
			return $isHashOk && $validPollType;

		}
		return false;
	}

	/* Poll Vote Actions */
	public static function vote($params)
	{
		$pollId = $params['pollId'];
		$userId = $params['userId'];
		$ansIds = $params['answerIds'];
		return self::setVote($pollId, $userId, $ansIds);
	}

	public static function setVote($pollId, $userId, $ansIds)
	{
		if (!$pollId || !$userId || !$ansIds)
			throw new Exception('Missing parameter for vote action');
		$answers = explode(self::ANSWER_SEPARATOR_CHAR, $ansIds);
		if (self::isValidPollIdStructure($pollId)) {
			// check early user vote
			$previousAnswers = self::$pollsCacheHandler->setCacheVote($userId, $pollId, $answers);
			if ($previousAnswers) {
				self::$pollsCacheHandler->decrementAnsCounter($pollId, $previousAnswers);
			} else {
				self::$pollsCacheHandler->incrementPollVotersCount($pollId);
			}
			self::$pollsCacheHandler->increaseAnsCounter($pollId, $answers);
			return;
		}
		return "Failed to vote due to bad poll id structure";

	}

	/* Poll Get Votes Actions */

	public static function getVotes($pollId, $ansIds)
	{
		self::init();
		$answers = explode(self::ANSWER_SEPARATOR_CHAR, $ansIds);
		$pollVotes = new PollVotes($pollId);

		$pollVotes->setNumVoters(self::$pollsCacheHandler->getPollVotersCount($pollId));
		foreach($answers as $ansId) {
			$answerCount = self::$pollsCacheHandler->getAnswerCounter($pollId, $ansId);
			$pollVotes->addAnswerCounter($ansId, $answerCount);
		}
		return $pollVotes;

	}

}

class PollCacheHandler
{

	// TODo - add initialization validations to class

	private $cache;

	public function __construct()
	{
		$this->cache = kCacheManager::getSingleLayerCache(kCacheManager::CACHE_TYPE_API_V3);
	}

	public function setCacheVote($userId, $pollId, $ansIds)
	{
		$userVoteKey = self::getPollUserVoteCacheKey($pollId, $userId);
		if ($this->cache->add($userVoteKey, $ansIds) === false) {
			$earlyVoteAnsIds = $this->cache->get($userVoteKey);
			$this->cache->set($userVoteKey, $ansIds);
			return $earlyVoteAnsIds;
		}
		return null;
	}

	public function getAnswerCounter($pollId, $ansId)
	{
		$key = $this->getPollAnswerCounterCacheKey($pollId, $ansId);
		$counter = $this->cache->get($key);
		if (!$counter)
			return 0;
		return $counter;
	}


	public function increaseAnsCounter($pollId, $ansIds)
	{
		//TODO add expiry to the poll counters keys
		foreach($ansIds as $ansId)
		{
			$ansCounterId = self::getPollAnswerCounterCacheKey($pollId, $ansId);
			// in case it does not exist it is set to the default init value (1 in this case)
			$this->cache->add($ansCounterId, 0);
			$this->cache->increment($ansCounterId);
		}
	}

	public function decrementAnsCounter($pollId, $ansIds)
	{
		foreach($ansIds as $ansId)
		{
			$ansCounterId = self::getPollAnswerCounterCacheKey($pollId, $ansId);
			$this->cache->decrement($ansCounterId);
		}

	}

	public function incrementPollVotersCount($pollId)
	{
		$this->cache->add($this->getPollVotersCacheKey($pollId), 0);
		$this->cache->increment($this->getPollVotersCacheKey($pollId));
	}

	public function getPollVotersCount($pollId)
	{
		return $this->cache->get($this->getPollVotersCacheKey($pollId));
	}

	/* Cache keys functions */
	private function getPollVotersCacheKey($pollId)
	{
		return $pollId . "_voters";
	}

	private function getPollUserVoteCacheKey($pollId, $userId)
	{
		return $pollId.'_'.$userId;
	}

	private function getPollAnswerCounterCacheKey($pollId, $ansId)
	{
		return $pollId.'_'.$ansId;
	}

}

class PollVotes {
	public $pollId = "";
	public $numVoters = 0 ;
	public $answerCounters = array();

	public function __construct($pollId)
	{
		$this->pollId = $pollId;
	}
	public function addAnswerCounter($ansId, $counter)
	{
		$this->answerCounters[$ansId] = $counter;
	}
	public function setNumVoters($voters)
	{
		if(!$voters)
			$this->numVoters = 0;
		else
			$this->numVoters = $voters;
	}

	/**
	 * @param PollVotes $other
	 */
	public function merge($other)
	{
		$this->numVoters += $other->numVoters;
		foreach ($other->answerCounters as $ans => $counter)
		{
			$currentCounter = $this->answerCounters[$ans];
			if (isset($currentCounter))
				$this->answerCounters[$ans] = $currentCounter + $counter;
			else
				$this->answerCounters[$ans] = $counter;
		}
	}

}