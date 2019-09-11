<?php

namespace Ayacoo\AyacooProjectfiles\Writer;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Exception\InvalidLogWriterConfigurationException;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\AbstractWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Log writer that writes the log records into a file.
 */
class CustomWriterV9 extends AbstractWriter
{
    /**
     * Log file path, relative to TYPO3's base project folder
     *
     * @var string
     */
    protected $logFile = '';

    /**
     * @var string
     */
    protected $logFileInfix = '';

    /**
     * Default log file path
     *
     * @var string
     */
    protected $defaultLogFileTemplate = '/log/monolog_%s.log';

    /**
     * Log file handle storage
     *
     * To avoid concurrent file handles on a the same file when using several FileWriter instances,
     * we share the file handles in a static class variable
     *
     * @static
     * @var array
     */
    protected static $logFileHandles = [];

    /**
     * Keep track of used file handles by different fileWriter instances
     * As the logger gets instantiated by class name but the resources
     * are shared via the static $logFileHandles we need to track usage
     * of file handles to avoid closing handles that are still needed
     * by different instances. Only if the count is zero may the file
     * handle be closed.
     *
     * @var array
     */
    protected static $logFileHandlesCount = [];

    /** @var Logger $log */
    protected $log;

    /**
     * @var array
     */
    protected static $monologLevels =  [
        LogLevel::EMERGENCY => 600,
        LogLevel::ALERT     => 550,
        LogLevel::CRITICAL  => 500,
        LogLevel::ERROR     => 400,
        LogLevel::WARNING   => 300,
        LogLevel::NOTICE    => 250,
        LogLevel::INFO      => 200,
        LogLevel::DEBUG     => 100
    ];

    /**
     * @var int
     */
    protected $maxFiles = 31;

    /**
     * @var bool
     */
    protected $folderDateFormat = true;

    /**
     * @var bool
     */
    protected $ignoreEmptyContextAndExtra = true;

    /**
     * Constructor, opens the log file handle
     *
     * @param array $options
     *
     * @throws InvalidLogWriterConfigurationException
     */
    public function __construct(array $options = [])
    {
        // the parent constructor reads $options and sets them
        parent::__construct($options);
        if (empty($options['logFile'])) {
            $this->setLogFile($this->getDefaultLogFileName());
        }

        $this->log = new Logger('LogRotateFileWriter');
        $handler = new RotatingFileHandler($this->getLogFile(), $this->maxFiles);
        if ($this->folderDateFormat) {
            $handler->setFilenameFormat('{date}-{filename}', 'Y/m/Y-m-d');
        }
        $handler->setFormatter(new LineFormatter(null, null, false, $this->ignoreEmptyContextAndExtra));
        $this->log->pushHandler($handler);
    }

    /**
     * Destructor, closes connection to syslog.
     */
    public function __destruct()
    {
        closelog();
    }

    public function setLogFileInfix(string $infix)
    {
        $this->logFileInfix = $infix;
    }

    /**
     * Sets the path to the log file.
     *
     * @param string $relativeLogFile path to the log file, relative to public web dir
     * @return WriterInterface
     * @throws InvalidLogWriterConfigurationException
     */
    public function setLogFile($relativeLogFile)
    {
        $logFile = $relativeLogFile;
        // Skip handling if logFile is a stream resource. This is used by unit tests with vfs:// directories
        if (false === strpos($logFile, '://') && !PathUtility::isAbsolutePath($logFile)) {
            $logFile = GeneralUtility::getFileAbsFileName($logFile);
            if (empty($logFile)) {
                throw new InvalidLogWriterConfigurationException(
                    'Log file path "' . $relativeLogFile . '" is not valid!',
                    1444374805
                );
            }
        }
        $this->logFile = $logFile;

        return $this;
    }

    /**
     * Gets the path to the log file.
     *
     * @return string Path to the log file.
     */
    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * Writes the log record
     *
     * @param LogRecord $record Log record
     * @return WriterInterface $this
     * @throws \RuntimeException
     */
    public function writeLog(LogRecord $record)
    {
        $timestamp = date('r', (int)$record->getCreated());
        $levelName = LogLevel::getName($record->getLevel());
        $data = '';
        $recordData = $record->getData();
        if (!empty($recordData)) {
            // According to PSR3 the exception-key may hold an \Exception
            // Since json_encode() does not encode an exception, we run the _toString() here
            if (isset($recordData['exception']) && $recordData['exception'] instanceof \Exception) {
                $recordData['exception'] = (string)$recordData['exception'];
            }
            $data = '- ' . json_encode($recordData);
        }

        $message = sprintf(
            '%s [%s] request="%s" component="%s": %s %s',
            $timestamp,
            $levelName,
            $record->getRequestId(),
            $record->getComponent(),
            $record->getMessage(),
            $data
        );

        if (false === $this->log->addRecord(self::$monologLevels[$record->getLevel()], $message)) {
            throw new \RuntimeException('Could not write log record to log file', 1345036335);
        }

        return $this;
    }

    /**
     * Returns the path to the default log file.
     * Uses the defaultLogFileTemplate and replaces the %s placeholder with a short MD5 hash
     * based on a static string and the current encryption key.
     *
     * @return string
     */
    protected function getDefaultLogFileName()
    {
        $namePart = substr(GeneralUtility::hmac($this->defaultLogFileTemplate, 'defaultLogFile'), 0, 10);
        if ($this->logFileInfix !== '') {
            $namePart = $this->logFileInfix . '_' . $namePart;
        }
        return Environment::getVarPath() . sprintf($this->defaultLogFileTemplate, $namePart);
    }
}
