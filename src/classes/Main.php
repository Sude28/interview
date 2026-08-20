<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest;

use Bramus\Router\Router;
use RuntimeException;
use Smarty\Smarty;

final class Main
{
    private Router $router;
    private Smarty $smarty;
    private Home $home;

    public function __construct()
    {
        $language = $this->resolveLanguage();
        $translations = require __DIR__ . "/../languages/{$language}.php";

        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir(__DIR__ . '/../templates');
        $compileDirectory = sys_get_temp_dir() . '/turkpin-smarty';

        if (!is_dir($compileDirectory)
            && !mkdir($compileDirectory, 0775, true)
            && !is_dir($compileDirectory)
        ) {
            throw new RuntimeException('Smarty compile directory could not be created.');
        }

        $this->smarty->setCompileDir($compileDirectory);
        $this->smarty->assign('LANG', $translations);
        $this->smarty->assign('currentLanguage', $language);
        $this->smarty->assign('langs', ['tr' => 'Türkçe', 'en' => 'English']);
        $this->smarty->assign('error', null);
        $this->smarty->assign('template', null);
        $this->smarty->assign('selectedGameId', '');

        $apiClient = new TurkpinApiClient(
            $_ENV['TURKPIN_API_URL'] ?? '',
            $_ENV['TURKPIN_API_USERNAME'] ?? '',
            $_ENV['TURKPIN_API_PASSWORD'] ?? '',
            max(1, min(60, (int) ($_ENV['TURKPIN_API_TIMEOUT'] ?? 15))),
        );

        $this->router = new Router();
        $this->home = new Home($this->smarty, $apiClient, $translations);
    }

    public function run(): void
    {
        $this->router->get('/', fn () => $this->home->index());
        $this->router->post('/orders', fn () => $this->home->createOrders());
        $this->router->set404(function (): void {
            http_response_code(404);
            $this->smarty->assign('error', $this->home->translation('not_found'));
        });

        $this->router->run();
        $this->smarty->display('index.html');
    }

    private function resolveLanguage(): string
    {
        $supportedLanguages = ['tr', 'en'];
        $language = $_SESSION['lang'] ?? 'tr';
        $requestedLanguage = $_GET['lang'] ?? null;

        if (is_string($requestedLanguage) && in_array($requestedLanguage, $supportedLanguages, true)) {
            $language = $requestedLanguage;
            $_SESSION['lang'] = $language;
        }

        return in_array($language, $supportedLanguages, true) ? $language : 'tr';
    }
}
