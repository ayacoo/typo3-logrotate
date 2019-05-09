# typo3-logrotate
Custom LogWriter Beispiel mit LogRotate von Monolog. Umgesetzt für die TYPO3 Version: 8.7.x und 9.5.x

### Problem
Wenn die TYPO3 Logging Methode (auch) exzessiv auf Live genutzt wird, kann die Logdatei entsprechend groß werden. (Im besten Falle sollte dies natürlich nicht passieren!)

### Ziel
CustomWriter implementieren der auf die LogRotate Features von Monolog zurück greift. Vorteil: Die Logs werden nach Tagen aufgeteilt so wie man es bereits u.a. vom Apache kennt

# Lösung

### Schritt 1 - Writer anlegen
Bestehenden Writer (typo3/sysext/core/Classes/Log/Writer/FileWriter.php) in eigene Klasse kopieren

### Schritt 2 - Writer für die LogLevel integrieren (Beispiel Warning)


```php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::WARNING => [
        \Ayacoo\AyacooData\Writer\CustomWriter::class => [],
    ]
];
```

Dies bedeutet das alle ErrorLevel ab Warning und höher geloggt werden. Dies ist auch empfehlenswert, da alles darunter recht unperformant wird. Ins Besondere wenn z.B. realurl läuft.

### Schritt 3 Monolog im CustomWriter integrieren
* Version 8: Monolog ist per Default bei TYPO3 dabei. Daher kann man die Klasse bequem im Konstruktor laden.
* Version 9: Mit ```php composer require monolog/monolog``` holen wir uns die Monolog Version. Achtet auf die Unterstützung der PHP Version
* Die Rotation wird automatisch über den RotatingFileHandler gesteuert. Die Anzahl der Dateien wird über maxFiles gesteuert => https://github.com/Seldaek/monolog/blob/master/src/Monolog/Handler/RotatingFileHandler.php
* Der Defaultpfad der Logdatei wurde angepasst
* Die Eigenschaft maxFiles kann man als Option im CustomWriter ergänzen, so dass diese dann später per TCA steuerbar ist. Als Defaultwert habe ich mich für 7 Dateien entschieden.

```php
/**
 * Default log file path
 *
 * @var string
 */
protected $defaultLogFileTemplate = 'typo3temp/logs/monolog.log';

/**
 * @var int
 */
protected $maxFiles = 7;
    
public function __construct(array $options = [])
{
    [...]
 
    $this->log = new Logger('CustomWriter');
    $this->log->pushHandler(new RotatingFileHandler($this->getLogFile(), $this->maxFiles));
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
    [...]
    if (false === $this->log->addRecord($monologLevel, $message)) {
        throw new \RuntimeException('Could not write log record to log file', 1345036335);
    }
 
    return $this;
}
```

### Schritt 4 - LogLevel angleichen
Die LogLevel Werte zwischen TYPO3 und Monolog sind unterschiedlich, daher ist ein Mapping notwendig. Zuerst packe ich ein statisches Array in die Klasse

```php
protected static $monologLevels =  [
    \TYPO3\CMS\Core\Log\LogLevel::EMERGENCY => 600,
    \TYPO3\CMS\Core\Log\LogLevel::ALERT => 550,
    \TYPO3\CMS\Core\Log\LogLevel::CRITICAL => 500,
    \TYPO3\CMS\Core\Log\LogLevel::ERROR => 400,
    \TYPO3\CMS\Core\Log\LogLevel::WARNING => 300,
    \TYPO3\CMS\Core\Log\LogLevel::NOTICE => 250,
    \TYPO3\CMS\Core\Log\LogLevel::INFO => 200,
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => 100
];
```

Anschließend muss ich nochmal die addRecord Methode anpassen.

```php
$log->addRecord(self::$monologLevels[$record->getLevel()], $message));
```

Schritt 5 - Wie logge ich?

```php
/** @var $logger \TYPO3\CMS\Core\Log\Logger */
$logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
$logger->emergency('Emergency', ['hello' => 'world']);
$logger->alert('Alert', ['hello' => 'world']);
$logger->critical('Critical', ['hello' => 'world']);
$logger->error('Error', ['hello' => 'world']);
$logger->warning('Warning', ['hello' => 'world']);
$logger->info('Info', ['hello' => 'world']);
$logger->notice('Notice', ['hello' => 'world']);
$logger->debug('Debug', ['hello' => 'world']);
```

In Version 9 beachten: Es gibt hier einen Logger Trait. Siehe https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Logging/Quickstart/Index.html

# Quellen und Danksagung
* Inspiration / Ideen kamen von Alexander Schnitzler
    * https://twitter.com/alex_schnitzler
* Monolog Doku / Library
    * https://github.com/Seldaek/monolog
* TYPO3 Dokumentation
    * https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Logging/Index.html