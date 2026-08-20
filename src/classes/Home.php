<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest;

use Smarty\Smarty;
use Throwable;

final class Home
{
    /**
     * @param array<string, string> $translations
     */
    public function __construct(
        private readonly Smarty $smarty,
        private readonly TurkpinApiClient $apiClient,
        private readonly array $translations,
    ) {
    }

    public function index(): void
    {
        $selectedGameId = $this->queryString('game');
        $games = [];
        $products = [];
        $productListLoaded = false;
        $error = null;

        try {
            $games = $this->apiClient->getGames();

            if ($selectedGameId !== '') {
                $gameIds = array_column($games, 'id');

                if (!in_array($selectedGameId, $gameIds, true)) {
                    throw new ApiException($this->translations['invalid_game']);
                }

                $products = array_map(
                    fn (array $product): array => $this->prepareProductForView($product),
                    $this->apiClient->getProducts($selectedGameId),
                );
                $productListLoaded = true;
            }
        } catch (ApiException $exception) {
            $error = $this->formatApiError($exception);
        } catch (Throwable) {
            $error = $this->translations['unexpected_error'];
        }

        $this->smarty->assign('games', $games);
        $this->smarty->assign('products', $products);
        $this->smarty->assign('productListLoaded', $productListLoaded);
        $this->smarty->assign('selectedGameId', $selectedGameId);
        $this->smarty->assign('csrfToken', $this->csrfToken());
        $this->smarty->assign('orderFeedback', $this->consumeOrderFeedback());
        $this->smarty->assign('error', $error);
        $this->smarty->assign('template', 'home.html');
    }

    public function createOrders(): void
    {
        $gameId = $this->postString('game_id');

        if (!$this->isValidCsrfToken($this->postString('csrf_token'))) {
            $this->flashError($this->translations['csrf_error']);
            $this->redirect($gameId);
        }

        if ($gameId === '' || !ctype_digit($gameId)) {
            $this->flashError($this->translations['invalid_game']);
            $this->redirect('');
        }

        $selectedProductIds = $this->selectedProductIds();

        if ($selectedProductIds === []) {
            $this->flashError($this->translations['select_product_error']);
            $this->redirect($gameId);
        }

        $results = [];

        try {
            $products = $this->apiClient->getProducts($gameId);
            $productsById = [];

            foreach ($products as $product) {
                $productsById[$product['id']] = $product;
            }

            foreach ($selectedProductIds as $productId) {
                $product = $productsById[$productId] ?? null;

                if ($product === null) {
                    $results[] = $this->failedResult($productId, $this->translations['invalid_product']);
                    continue;
                }

                try {
                    $quantity = $this->validatedQuantity($product);
                    $barem = $this->validatedBarem($product);
                    $character = $this->validatedCharacter($productId);
                    $order = $this->apiClient->createOrder(
                        $gameId,
                        $productId,
                        $quantity,
                        $character,
                        $product['pre_order'],
                        $barem,
                    );

                    $results[] = [
                        'success' => true,
                        'product_name' => $product['name'],
                        'message' => $order['message'],
                        'status' => $order['status'],
                        'order_number' => $order['order_number'],
                        'amount' => $order['amount'],
                        'epins' => $order['epins'],
                    ];
                } catch (ApiException $exception) {
                    $results[] = $this->failedResult($product['name'], $this->formatApiError($exception));
                } catch (Throwable) {
                    $results[] = $this->failedResult($product['name'], $this->translations['unexpected_error']);
                }
            }
        } catch (ApiException $exception) {
            $results[] = $this->failedResult('', $this->formatApiError($exception));
        } catch (Throwable) {
            $results[] = $this->failedResult('', $this->translations['unexpected_error']);
        }

        $_SESSION['order_feedback'] = ['results' => $results];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $this->redirect($gameId);
    }

    public function translation(string $key): string
    {
        return $this->translations[$key] ?? $key;
    }

    /**
     * @param array{
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
     * } $product
     * @return array<string, mixed>
     */
    private function prepareProductForView(array $product): array
    {
        $hasBarem = $product['min_barem'] !== null
            && $product['max_barem'] !== null
            && $product['barem_step'] !== null
            && $product['barem_step'] > 0
            && $product['max_barem'] >= $product['min_barem'];
        $inputMax = $product['max_order'] > 0 ? $product['max_order'] : null;

        if (!$product['pre_order']) {
            $inputMax = $inputMax !== null ? min($inputMax, $product['stock']) : $product['stock'];
        }

        return [
            ...$product,
            'can_order' => $product['pre_order'] || $product['stock'] >= $product['min_order'],
            'has_barem' => $hasBarem,
            'input_max' => $inputMax,
            'barem_range' => $hasBarem
                ? sprintf(
                    $this->translations['tier_range'],
                    $product['min_barem'],
                    $product['max_barem'],
                    $product['barem_step'],
                )
                : '',
        ];
    }

    /**
     * @param array{id: string, name: string, stock: int, min_order: int, max_order: int, pre_order: bool} $product
     */
    private function validatedQuantity(array $product): int
    {
        $quantities = $_POST['quantities'] ?? [];
        $value = is_array($quantities) ? ($quantities[$product['id']] ?? null) : null;

        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            throw new ApiException($this->translations['invalid_quantity']);
        }

        $quantity = (int) $value;

        if ($quantity < $product['min_order']) {
            throw new ApiException(sprintf($this->translations['min_quantity_error'], $product['min_order']));
        }

        if ($product['max_order'] > 0 && $quantity > $product['max_order']) {
            throw new ApiException(sprintf($this->translations['max_quantity_error'], $product['max_order']));
        }

        if (!$product['pre_order'] && $quantity > $product['stock']) {
            throw new ApiException($this->translations['stock_error']);
        }

        return $quantity;
    }

    /**
     * @param array{id: string, min_barem: ?float, max_barem: ?float, barem_step: ?float} $product
     */
    private function validatedBarem(array $product): ?float
    {
        if ($product['min_barem'] === null || $product['max_barem'] === null || $product['barem_step'] === null) {
            return null;
        }

        if ($product['barem_step'] <= 0 || $product['max_barem'] < $product['min_barem']) {
            throw new ApiException($this->translations['invalid_barem']);
        }

        $barems = $_POST['barems'] ?? [];
        $value = is_array($barems) ? ($barems[$product['id']] ?? null) : null;

        if (!is_scalar($value) || !is_numeric((string) $value)) {
            throw new ApiException($this->translations['invalid_barem']);
        }

        $barem = (float) $value;

        if ($barem < $product['min_barem'] || $barem > $product['max_barem']) {
            throw new ApiException(sprintf(
                $this->translations['barem_range_error'],
                $product['min_barem'],
                $product['max_barem'],
            ));
        }

        $steps = ($barem - $product['min_barem']) / $product['barem_step'];

        if (abs($steps - round($steps)) > 0.000001) {
            throw new ApiException(sprintf($this->translations['barem_step_error'], $product['barem_step']));
        }

        return $barem;
    }

    private function validatedCharacter(string $productId): ?string
    {
        $characters = $_POST['characters'] ?? [];
        $value = is_array($characters) ? ($characters[$productId] ?? '') : '';
        $character = is_scalar($value) ? trim((string) $value) : '';

        if (strlen($character) > 100) {
            throw new ApiException($this->translations['character_length_error']);
        }

        return $character !== '' ? $character : null;
    }

    /**
     * @return list<string>
     */
    private function selectedProductIds(): array
    {
        $selected = $_POST['selected_products'] ?? [];

        if (!is_array($selected)) {
            return [];
        }

        $productIds = [];

        foreach ($selected as $productId) {
            $productId = is_scalar($productId) ? (string) $productId : '';

            if ($productId !== '' && ctype_digit($productId)) {
                $productIds[] = $productId;
            }
        }

        return array_values(array_unique($productIds));
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function isValidCsrfToken(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    private function queryString(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function formatApiError(ApiException $exception): string
    {
        $apiCode = $exception->getApiCode();

        return $apiCode !== null
            ? "{$exception->getMessage()} ({$this->translations['error_code']}: {$apiCode})"
            : $exception->getMessage();
    }

    /**
     * @return array{success: false, product_name: string, message: string, status: string, order_number: string, amount: string, epins: array{}}
     */
    private function failedResult(string $productName, string $message): array
    {
        return [
            'success' => false,
            'product_name' => $productName,
            'message' => $message,
            'status' => '',
            'order_number' => '',
            'amount' => '',
            'epins' => [],
        ];
    }

    private function flashError(string $message): void
    {
        $_SESSION['order_feedback'] = ['results' => [$this->failedResult('', $message)]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeOrderFeedback(): ?array
    {
        $feedback = $_SESSION['order_feedback'] ?? null;
        unset($_SESSION['order_feedback']);

        return is_array($feedback) ? $feedback : null;
    }

    private function redirect(string $gameId): never
    {
        $location = $gameId !== '' ? '/?game=' . rawurlencode($gameId) : '/';
        header('Location: ' . $location, true, 303);
        exit;
    }
}
