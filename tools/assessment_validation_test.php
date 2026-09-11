<?php
declare(strict_types=1);

/**
 * RiskOps - Degerlendirme dogrulama regresyon testi
 * Calistirma:  php tools/assessment_validation_test.php
 *
 * Bagimlilik YOK: veritabani sunucusu gerekmez. GERCEK uretim
 * dogrulayicisi (assessments/_validate.php -> assessment_collect_input)
 * calistirilir; db() yalnizca gercek MySQL'in verecegi risk satirini
 * saglayan minik bir koza (seam) ile ikame edilir.
 *
 * REGRESYON ARKAPLANI: 'review' turu degerlendirme bir zamanlar inherent
 * skoru mevcut residual skorun ALTINA indirebiliyordu. Kalici tabloda
 * residual > inherent kaliyor ve tum etkin skor hesaplari
 * (COALESCE(residual, inherent)) riskin kendisinden buyuk cikiyordu.
 * Bu invariant risks/_validate.php ve residual tarafiyla korunur;
 * review tarafi da ayni sekilde korunmalidir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

define('RISKOPS_BOOTSTRAPPED', true);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../assessments/_validate.php';

/* ---- Minimal db() koza: gercek MySQL'in verecegi risk satiri --------- */

final class AssessmentTestStmt
{
    public function __construct(private array|false $row) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetch(): array|false { return $this->row; }
}

final class AssessmentTestDb
{
    public function __construct(private array|false $row) {}
    public function prepare(string $sql): AssessmentTestStmt
    {
        return new AssessmentTestStmt($this->row);
    }
}

/** Risk fiksturu: inherent 5x5=25, residual 4x4=16 (gecerli durum). */
function assessment_test_risk(): array
{
    return [
        'id' => 7, 'risk_code' => 'RISK-2026-0001', 'title' => 'Test riski',
        'status' => 'Open',
        'likelihood' => 5, 'impact' => 5, 'inherent_score' => 25,
        'residual_likelihood' => 4, 'residual_impact' => 4,
    ];
}

/** GERCEK uretim dogrulayicisini verilen POST ile calistirir. */
function assessment_test_collect(array $post, array|false|null $riskRow = null): array
{
    $_POST = $post;
    $_GET = [];
    global $assessmentTestRiskRow;
    $assessmentTestRiskRow = $riskRow ?? assessment_test_risk();
    return assessment_collect_input();
}

function db(): AssessmentTestDb
{
    global $assessmentTestRiskRow;
    return new AssessmentTestDb($assessmentTestRiskRow ?? assessment_test_risk());
}

$PASS = 0;
$FAIL = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $PASS, $FAIL;
    if ($ok) {
        $PASS++;
        printf("  [ OK ]  %-56s %s\n", $label, $detail);
    } else {
        $FAIL++;
        printf("  [FAIL]  %-56s %s\n", $label, $detail);
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('-', 72) . "\n  " . $title . "\n" . str_repeat('-', 72) . "\n";
}

echo "\n============ Assessment Validation Regression Test =============";

/* ------------------------------------------------------------------ */
section('1) Review inherent skorunu residualin altina indiremez');
/* ------------------------------------------------------------------ */

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'review',
    'likelihood' => '2', 'impact' => '3',
]);
check('review 2x3=6 vs residual 16 reddedilir', $e !== [],
    $e === [] ? 'hata donmedi; residual > inherent kalici olurdu' : 'reddedildi');

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'review',
    'likelihood' => '4', 'impact' => '4',
]);
check('review residuala esit (16 == 16) kabul edilir', $e === []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'review',
    'likelihood' => '5', 'impact' => '5',
]);
check('review residualin uzerinde (25) kabul edilir', $e === []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'review',
    'likelihood' => '1', 'impact' => '1',
], array_merge(assessment_test_risk(), [
    'residual_likelihood' => null, 'residual_impact' => null,
]));
check('residuali olmayan riskte 1x1 review kabul edilir', $e === []);

/* ------------------------------------------------------------------ */
section('2) Mevcut korumalar yerinde kalmali');
/* ------------------------------------------------------------------ */

$smallRisk = array_merge(assessment_test_risk(), [
    'likelihood' => 3, 'impact' => 3, 'inherent_score' => 9,
    'residual_likelihood' => 2, 'residual_impact' => 2,
]);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'residual',
    'likelihood' => '4', 'impact' => '4',
], $smallRisk);
check('residual 16 > inherent 9 reddedilir', $e !== []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'residual',
    'likelihood' => '3', 'impact' => '3',
], $smallRisk);
check('residual == inherent kabul edilir (sinir)', $e === []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'initial',
    'likelihood' => '1', 'impact' => '1',
]);
check("tip 'initial' elle girilemez", $e !== []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'hacked',
    'likelihood' => '1', 'impact' => '1',
]);
check('gecersiz tip reddedilir', $e !== []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'review',
    'likelihood' => '7', 'impact' => '1',
]);
check('olasilik 1-5 disi reddedilir', $e !== []);

[, $e] = assessment_test_collect([
    'risk_id' => '7', 'assessment_type' => 'review',
    'likelihood' => '2', 'impact' => '3',
    'assessed_at' => date('Y-m-d', strtotime('+1 day')),
]);
check('gelecek tarihli assessed_at reddedilir', isset($e['assessed_at']));

[, $e] = assessment_test_collect([
    'risk_id' => '999', 'assessment_type' => 'review',
    'likelihood' => '2', 'impact' => '3',
], false);
check('bulunmayan risk reddedilir', $e !== []);

/* ------------------------------------------------------------------ */

echo "\n" . str_repeat('-', 72) . "\n";
printf("Sonuc: %d OK, %d FAIL\n", $PASS, $FAIL);
if ($FAIL > 0) {
    echo "ASSESSMENT VALIDATION TEST: FAIL\n";
    exit(1);
}
echo "ASSESSMENT VALIDATION TEST: PASS\n";
exit(0);
