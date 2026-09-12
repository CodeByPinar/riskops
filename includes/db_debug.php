<?php
declare(strict_types=1);

/**
 * RiskOps - Sorgu kaydedici PDO sarmalayıcıları
 * /var/www/riskops/includes/db_debug.php
 *
 * YALNIZCA hata ayıklama kipi açıkken yüklenir (bkz. includes/db.php).
 * Kapalıyken bu dosya hiç okunmaz ve db() düz PDO döndürür; yani
 * üretimde sorgu yolu bu sınıflardan geçmez.
 *
 * NASIL ÇALIŞIR
 * -------------
 * Üç yakalama noktası var, çünkü PDO'da bir SQL üç farklı yoldan
 * sunucuya gidebiliyor:
 *
 *   1) prepare() + execute()  -> RiskOpsDebugStatement::execute()
 *      Uygulamanın neredeyse tamamı bu yoldan geçer (SQL birleştirme
 *      yasak, her şey hazırlanmış ifade).
 *
 *   2) query() / exec()       -> RiskOpsDebugPdo
 *      Parametresiz sabit SQL'ler (SET SESSION..., SHOW ...) buradan
 *      geçer. PDO bunları PHP seviyesinde execute() çağırmadan
 *      çalıştırdığı için ayrıca sarmalanmaları gerekir.
 *
 *   3) prepare()'in KENDİSİ   -> RiskOpsDebugPdo::prepare()
 *      EMULATE_PREPARES=false olduğundan SQL zaten prepare aşamasında
 *      sunucuya gidiyor; bozuk bir SQL execute()'a hiç ulaşmaz.
 *
 * Ölçüm sorgunun KENDİSİNİ kapsar, satırların çekilmesini değil:
 * fetch() çağrıları bu sürenin dışındadır.
 */

/**
 * execute() çağrılarını süreleyip kaydeden PDOStatement.
 *
 * Bağlanan parametreler de toplanır: bir sorgunun neden beklenmedik
 * sonuç verdiğini anlamak için değerleri görmek gerekir. Değerler
 * araç çubuğuna basılmadan önce debug_mask_array() içinden geçer.
 */
class RiskOpsDebugStatement extends PDOStatement
{
    /** @var array<array-key, mixed> bindValue/bindParam ile bağlananlar */
    private array $boundParams = [];

    /* PDO bu sınıfı kendisi örnekler; kurucu protected OLMALIDIR. */
    protected function __construct()
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundParams[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }

    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        /* Referansla bağlanır: değer execute() anında okunmalıdır,
           şimdi değil. Yine de bir kopya tutuyoruz ki bağlamanın
           yapıldığı görülsün. */
        $this->boundParams[$param] = $var;

        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function execute(?array $params = null): bool
    {
        $started = microtime(true);
        $error   = null;
        $ok      = false;

        try {
            $ok = parent::execute($params);
        } catch (Throwable $e) {
            /* Hata KAYDEDİLİR ama YUTULMAZ: uygulamanın hata işleme
               davranışı hata ayıklama kipinde de aynı kalmalı. */
            $error = get_class($e) . ': ' . $e->getMessage();
            throw $e;
        } finally {
            $rows = -1;
            if ($error === null) {
                try {
                    $rows = $this->rowCount();
                } catch (Throwable) {
                    /* Tamponsuz sorgularda rowCount güvenilir değil. */
                }
            }

            debug_record_query(
                (string)$this->queryString,
                $params ?? $this->boundParams,
                (microtime(true) - $started) * 1000,
                $rows,
                $error
            );
        }

        return $ok;
    }
}

/**
 * query(), exec() ve BAŞARISIZ prepare() çağrılarını kaydeden PDO.
 *
 * BAŞARILI prepare() kaydedilmez: ATTR_STATEMENT_CLASS zaten
 * RiskOpsDebugStatement döndürüyor ve ölçüm execute() içinde yapılıyor.
 *
 * BAŞARISIZ prepare() ise kaydedilmek ZORUNDA. EMULATE_PREPARES=false
 * olduğu için SQL sunucuya prepare aşamasında gönderilir; sözdizimi
 * hatası, olmayan tablo veya olmayan kolon işte orada patlar ve
 * execute() hiç çağrılmaz. Sarmalanmasaydı hata ayıklama kipinde en
 * çok ihtiyaç duyulan sorgu - hatayı verenin ta kendisi - listede hiç
 * görünmezdi.
 */
class RiskOpsDebugPdo extends PDO
{
    public function exec(string $statement): int|false
    {
        $started = microtime(true);
        $error   = null;
        $result  = false;

        try {
            $result = parent::exec($statement);
        } catch (Throwable $e) {
            $error = get_class($e) . ': ' . $e->getMessage();
            throw $e;
        } finally {
            debug_record_query(
                $statement,
                [],
                (microtime(true) - $started) * 1000,
                is_int($result) ? $result : -1,
                $error
            );
        }

        return $result;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        try {
            return parent::prepare($query, $options);
        } catch (Throwable $e) {
            /* Süre 0: hata prepare'da oluştu, sorgu hiç çalışmadı.
               Önemli olan SQL'in ve hatanın kaydedilmesi. */
            debug_record_query($query, [], 0.0, -1, get_class($e) . ': ' . $e->getMessage());
            throw $e;
        }
    }

    public function query(
        string $query,
        ?int $fetchMode = null,
        mixed ...$fetchModeArgs
    ): PDOStatement|false {
        $started = microtime(true);
        $error   = null;
        $stmt    = false;

        try {
            $stmt = $fetchMode === null
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } catch (Throwable $e) {
            $error = get_class($e) . ': ' . $e->getMessage();
            throw $e;
        } finally {
            debug_record_query(
                $query,
                [],
                (microtime(true) - $started) * 1000,
                -1,
                $error
            );
        }

        return $stmt;
    }
}
