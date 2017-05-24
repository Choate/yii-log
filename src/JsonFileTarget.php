<?php

namespace choate\yii\log;


use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\log\FileTarget;
use yii\log\Logger;
use yii\web\Request;
use yii\web\Response;

class JsonFileTarget extends FileTarget
{
    private $logTag;

    /**
     * @inheritDoc
     */
    public function collect($messages, $final) {
        $this->messages = array_merge($this->messages, static::filterMessages($messages, $this->getLevels(), $this->categories, $this->except));
        $count = count($this->messages);
        if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
            $oldExportInterval = $this->exportInterval;
            $this->exportInterval = 0;
            $this->export();
            $this->exportInterval = $oldExportInterval;

            $this->messages = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function export() {
        $operation = array_map([$this, 'formatMessage'], $this->messages);
        krsort($operation);
        $messages = array_merge($this->getUserInfo(), $this->getRequestInfo(), $this->getContextMessage(), ['operation' => $operation]);
        $text = Json::encode($messages) . "\n";
        if (($fp = @fopen($this->logFile, 'a')) === false) {
            throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
        }
        @flock($fp, LOCK_EX);
        if ($this->enableRotation) {
            // clear stat cache to ensure getting the real current file size and not a cached one
            // this may result in rotating twice when cached file size is used on subsequent calls
            clearstatcache();
        }
        if ($this->enableRotation && @filesize($this->logFile) > $this->maxFileSize * 1024) {
            $this->rotateFiles();
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @file_put_contents($this->logFile, $text, FILE_APPEND | LOCK_EX);
        } else {
            @fwrite($fp, $text);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
        if ($this->fileMode !== null) {
            @chmod($this->logFile, $this->fileMode);
        }
    }

    /**
     * @inheritDoc
     */
    public function formatMessage($message) {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string)$text;
            } else {
                $text = VarDumper::export($text);
            }
        }
        $traces = [];
        if (isset($message[4])) {
            foreach ($message[4] as $trace) {
                $traces[] = "in {$trace['file']}:{$trace['line']}";
            }
        }

        return ['level' => $level, 'category' => $category, 'timestamp' => date('Y-m-d H:i:s', $timestamp), 'text' => $text, 'traces' => $traces];

    }

    /**
     * @return string
     */
    public function getLogTag() {
        if (empty($this->logTag)) {
            $this->logTag = uniqid();
        }

        return $this->logTag;
    }

    /**
     * @param string $logTag
     */
    public function setLogTag($logTag) {
        $this->logTag = $logTag;
    }

    /**
     * @inheritdoc
     */
    protected function getContextMessage() {
        $context = ArrayHelper::filter($GLOBALS, $this->logVars);
        $result = [];
        foreach ($context as $key => $values) {
            $result[trim($key, '_')] = $values;
        }

        return $result;
    }

    private function getRequestInfo() {
        $response = Yii::$app->getResponse();
        $statusCode = $response instanceof Response ? $response->getStatusCode() : $response->exitStatus;
        $application = Yii::$app->id;
        $route = Yii::$app->requestedAction ? Yii::$app->requestedAction->getUniqueId() : Yii::$app->requestedRoute;
        $startAt = YII_BEGIN_TIME;
        $endAt = microtime(true);
        $duration = number_format(($endAt - $startAt) * 1000) . ' ms';
        $requestTime = date('Y-m-d H:i:s', $startAt);

        return [
            'request_id'   => $this->getLogTag(),
            'application'  => $application,
            'route'        => $route,
            'status_code'  => $statusCode,
            'request_time' => $requestTime,
            'start_time'   => $startAt,
            'end_time'     => $endAt,
            'duration'     => $duration,
        ];
    }

    private function getUserInfo() {
        if (Yii::$app === null) {
            return '';
        }

        $request = Yii::$app->getRequest();
        $ip = $request instanceof Request ? $request->getUserIP() : '-';

        /* @var $user \yii\web\User */
        $user = Yii::$app->has('user', true) ? Yii::$app->get('user') : null;
        if ($user && ($identity = $user->getIdentity(false))) {
            $userID = $identity->getId();
        } else {
            $userID = '-';
        }

        /* @var $session \yii\web\Session */
        $session = Yii::$app->has('session', true) ? Yii::$app->get('session') : null;
        $sessionID = $session && $session->getIsActive() ? $session->getId() : '-';

        return ['ip' => $ip, 'user_id' => $userID, 'session_id' => $sessionID];
    }
}