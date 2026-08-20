<?php

declare(strict_types=1);

use Turkpin\InterviewTest\ApiException;
use Turkpin\InterviewTest\TurkpinApiClient;
use Smarty\Smarty;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Turkpin\\InterviewTest\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $file = dirname(__DIR__) . '/src/classes/' . substr($class, strlen($prefix)) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

$testResults = [];

function fixture(string $name): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);

    if ($contents === false) {
        throw new RuntimeException("Fixture could not be read: {$name}");
    }

    return $contents;
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = $message !== '' ? $message : 'Values are not identical.';
        throw new RuntimeException(
            $detail . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true),
        );
    }
}

function assertContainsText(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("Expected text was not found: {$needle}");
    }
}

function testCase(string $name, callable $test): bool
{
    try {
        $test();
        echo "[PASS] {$name}" . PHP_EOL;
        return true;
    } catch (Throwable $exception) {
        echo "[FAIL] {$name}: {$exception->getMessage()}" . PHP_EOL;
        return false;
    }
}

$testResults[] = testCase('lists games and safely escapes credentials in XML', function (): void {
    $requestXml = '';
    $transport = static function (string $endpoint, string $xml, int $timeout) use (&$requestXml): string {
        assertSameValue('https://example.test/api.php', $endpoint);
        assertSameValue(15, $timeout);
        $requestXml = $xml;

        return fixture('games.xml');
    };
    $client = new TurkpinApiClient(
        'https://example.test/api.php',
        'user&name',
        'secret<pass',
        15,
        $transport,
    );

    $games = $client->getGames();

    assertSameValue(2, count($games));
    assertSameValue(['id' => '1', 'name' => 'Game 1'], $games[0]);
    assertContainsText('<cmd>epinOyunListesi</cmd>', $requestXml);
    assertContainsText('<username>user&amp;name</username>', $requestXml);
    assertContainsText('<password>secret&lt;pass</password>', $requestXml);
});

$testResults[] = testCase('normalizes standard and tiered products', function (): void {
    $transport = static fn (): string => fixture('products.xml');
    $client = new TurkpinApiClient('https://example.test/api.php', 'user', 'pass', 15, $transport);

    $products = $client->getProducts('1');

    assertSameValue(2, count($products));
    assertSameValue(5, $products[0]['max_order']);
    assertSameValue(false, $products[0]['pre_order']);
    assertSameValue(true, $products[1]['pre_order']);
    assertSameValue(25.0, $products[1]['min_barem']);
    assertSameValue(0.01, $products[1]['barem_step']);
});

$testResults[] = testCase('creates an order and normalizes its result', function (): void {
    $requestXml = '';
    $transport = static function (string $endpoint, string $xml) use (&$requestXml): string {
        $requestXml = $xml;

        return fixture('order-success.xml');
    };
    $client = new TurkpinApiClient('https://example.test/api.php', 'user', 'pass', 15, $transport);

    $order = $client->createOrder('1', '2', 3, 'Player One', true, 25.01);

    assertSameValue('Success', $order['status']);
    assertSameValue('25031013105101', $order['order_number']);
    assertSameValue('TEST-CODE-001', $order['epins'][0]['code']);
    assertContainsText('<adet>3</adet>', $requestXml);
    assertContainsText('<pre_order>true</pre_order>', $requestXml);
    assertContainsText('<barem>25.01</barem>', $requestXml);
});

$testResults[] = testCase('surfaces API error code and description', function (): void {
    $transport = static fn (): string => fixture('order-error.xml');
    $client = new TurkpinApiClient('https://example.test/api.php', 'user', 'pass', 15, $transport);

    try {
        $client->createOrder('1', '2', 1);
    } catch (ApiException $exception) {
        assertSameValue('012', $exception->getApiCode());
        assertSameValue('Urun stok yetersiz.', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected ApiException was not thrown.');
});

$testResults[] = testCase('rejects invalid XML responses', function (): void {
    $transport = static fn (): string => '<not-valid';
    $client = new TurkpinApiClient('https://example.test/api.php', 'user', 'pass', 15, $transport);

    try {
        $client->getGames();
    } catch (ApiException $exception) {
        assertSameValue('API geçerli bir XML yanıtı döndürmedi.', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected ApiException was not thrown.');
});

$testResults[] = testCase('renders the product table and order result modal', function (): void {
    if (!class_exists(Smarty::class)) {
        throw new RuntimeException('Run composer install before executing template tests.');
    }

    $smarty = new Smarty();
    $smarty->setTemplateDir(dirname(__DIR__) . '/src/templates');
    $smarty->setCompileDir(sys_get_temp_dir() . '/turkpin-smarty-tests');
    $smarty->assign('LANG', require dirname(__DIR__) . '/src/languages/tr.php');
    $smarty->assign('currentLanguage', 'tr');
    $smarty->assign('langs', ['tr' => 'Türkçe', 'en' => 'English']);
    $smarty->assign('error', null);
    $smarty->assign('template', 'home.html');
    $smarty->assign('selectedGameId', '1');
    $smarty->assign('csrfToken', 'test-token');
    $smarty->assign('games', [['id' => '1', 'name' => 'Game 1']]);
    $smarty->assign('products', [[
        'id' => '1',
        'name' => 'Product <One>',
        'stock' => 12,
        'min_order' => 1,
        'max_order' => 5,
        'price' => '10.50',
        'pre_order' => false,
        'can_order' => true,
        'has_barem' => false,
        'input_max' => 5,
        'min_barem' => null,
        'max_barem' => null,
        'barem_step' => null,
        'barem_range' => '',
    ]]);
    $smarty->assign('productListLoaded', true);
    $smarty->assign('orderFeedback', ['results' => [[
        'success' => true,
        'product_name' => 'Product <One>',
        'message' => 'Islem Basarili',
        'status' => 'Success',
        'order_number' => '12345',
        'amount' => '10.50',
        'epins' => [['id' => '1', 'code' => 'CODE-1', 'description' => 'Test']],
    ]]]);

    $html = $smarty->fetch('index.html');

    assertContainsText('Product &lt;One&gt;', $html);
    assertContainsText('id="orderResultModal"', $html);
    assertContainsText('CODE-1', $html);
});

exit(in_array(false, $testResults, true) ? 1 : 0);
