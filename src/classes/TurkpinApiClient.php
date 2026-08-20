<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest;

use Closure;
use DOMDocument;
use SimpleXMLElement;
use Throwable;

final class TurkpinApiClient
{
    private readonly Closure $transport;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 15,
        ?callable $transport = null,
    ) {
        $this->transport = $transport !== null
            ? Closure::fromCallable($transport)
            : Closure::fromCallable([$this, 'sendHttpRequest']);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function getGames(): array
    {
        $params = $this->request('epinOyunListesi');
        $games = [];

        foreach ($params->oyunListesi->oyun ?? [] as $game) {
            $id = trim((string) $game->id);
            $name = trim((string) $game->name);

            if ($id !== '' && $name !== '') {
                $games[] = ['id' => $id, 'name' => $name];
            }
        }

        return $games;
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     stock: int,
     *     min_order: int,
     *     max_order: int,
     *     price: string,
     *     tax_type: int,
     *     pre_order: bool,
     *     min_barem: ?float,
     *     max_barem: ?float,
     *     barem_step: ?float
     * }>
     */
    public function getProducts(string $gameId): array
    {
        if ($gameId === '' || !ctype_digit($gameId)) {
            throw new ApiException('Geçerli bir oyun seçilmelidir.');
        }

        $params = $this->request('epinUrunleri', ['oyunKodu' => $gameId]);
        $products = [];

        foreach ($params->epinUrunListesi->urun ?? [] as $product) {
            $id = trim((string) $product->id);
            $name = trim((string) $product->name);

            if ($id === '' || $name === '') {
                continue;
            }

            $products[] = [
                'id' => $id,
                'name' => $name,
                'stock' => max(0, (int) $product->stock),
                'min_order' => max(1, (int) $product->min_order),
                'max_order' => max(0, (int) $product->max_order),
                'price' => trim((string) $product->price),
                'tax_type' => (int) $product->tax_type,
                'pre_order' => filter_var((string) $product->pre_order, FILTER_VALIDATE_BOOLEAN),
                'min_barem' => $this->optionalFloat($product->min_barem ?? null),
                'max_barem' => $this->optionalFloat($product->max_barem ?? null),
                'barem_step' => $this->optionalFloat($product->barem_step ?? null),
            ];
        }

        return $products;
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     order_number: string,
     *     amount: string,
     *     epins: list<array{id: string, code: string, description: string}>
     * }
     */
    public function createOrder(
        string $gameId,
        string $productId,
        int $quantity,
        ?string $character = null,
        bool $preOrder = false,
        ?float $barem = null,
    ): array {
        $requestParams = [
            'oyunKodu' => $gameId,
            'urunKodu' => $productId,
            'adet' => $quantity,
        ];

        if ($character !== null && $character !== '') {
            $requestParams['character'] = $character;
        }

        if ($preOrder) {
            $requestParams['pre_order'] = 'true';
        }

        if ($barem !== null) {
            $requestParams['barem'] = $this->formatDecimal($barem);
        }

        $params = $this->request('epinSiparisYarat', $requestParams);
        $epins = [];

        foreach ($params->epin_list->epin ?? [] as $epin) {
            $epins[] = [
                'id' => trim((string) $epin->id),
                'code' => trim((string) $epin->code),
                'description' => trim((string) $epin->desc),
            ];
        }

        return [
            'status' => trim((string) $params->siparisSonuc),
            'message' => $this->errorDescription($params) ?: 'İşlem başarılı.',
            'order_number' => trim((string) $params->siparisNo),
            'amount' => trim((string) $params->siparisTutari),
            'epins' => $epins,
        ];
    }

    /**
     * @param array<string, scalar> $params
     */
    private function request(string $command, array $params = []): SimpleXMLElement
    {
        $this->assertConfigured();

        $xml = $this->buildRequestXml([
            'cmd' => $command,
            'username' => $this->username,
            'password' => $this->password,
            ...$params,
        ]);

        $response = ($this->transport)($this->endpoint, $xml, $this->timeout);
        $responseParams = $this->parseResponse($response);
        $errorCode = $this->errorCode($responseParams);

        if ($errorCode !== '000') {
            $description = $this->errorDescription($responseParams) ?: 'Turkpin API isteği başarısız oldu.';
            throw new ApiException($description, $errorCode);
        }

        return $responseParams;
    }

    /**
     * @param array<string, scalar> $params
     */
    private function buildRequestXml(array $params): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('APIRequest');
        $paramsNode = $document->createElement('params');

        foreach ($params as $name => $value) {
            $node = $document->createElement($name);
            $node->appendChild($document->createTextNode((string) $value));
            $paramsNode->appendChild($node);
        }

        $root->appendChild($paramsNode);
        $document->appendChild($root);

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new ApiException('API isteği XML formatına dönüştürülemedi.');
        }

        return $xml;
    }

    private function parseResponse(string $response): SimpleXMLElement
    {
        $previousSetting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($response, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        } catch (Throwable) {
            $xml = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }

        if ($xml === false || !isset($xml->params)) {
            throw new ApiException('API geçerli bir XML yanıtı döndürmedi.');
        }

        return $xml->params;
    }

    private function sendHttpRequest(string $endpoint, string $xml, int $timeout): string
    {
        $curl = curl_init($endpoint);

        if ($curl === false) {
            throw new ApiException('API bağlantısı başlatılamadı.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['DATA' => $xml],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($response)) {
            throw new ApiException('API bağlantı hatası: ' . $curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException("API HTTP {$statusCode} durum kodu döndürdü.");
        }

        return $response;
    }

    private function assertConfigured(): void
    {
        if ($this->endpoint === '' || $this->username === '' || $this->password === '') {
            throw new ApiException('API ayarları eksik. .env dosyasındaki TURKPIN_API_* alanlarını doldurun.');
        }

        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME);

        if ($scheme !== 'https') {
            throw new ApiException('API adresi HTTPS olmalıdır.');
        }
    }

    private function errorCode(SimpleXMLElement $params): string
    {
        $code = trim((string) ($params->error ?? $params->HATA_NO ?? ''));

        return $code !== '' ? str_pad($code, 3, '0', STR_PAD_LEFT) : '999';
    }

    private function errorDescription(SimpleXMLElement $params): string
    {
        return trim((string) ($params->error_desc ?? $params->HATA_ACIKLAMA ?? ''));
    }

    private function optionalFloat(?SimpleXMLElement $value): ?float
    {
        $stringValue = trim((string) ($value ?? ''));

        return $stringValue === '' ? null : (float) $stringValue;
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }
}
