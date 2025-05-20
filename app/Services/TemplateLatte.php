<?php

namespace Services;

use Latte\Engine;

class TemplateService
{
    private Engine $latte;

    public function __construct()
    {
        $this->latte = new Engine();
        $this->latte->setTempDirectory(ROOT_DIR . '/app/Views/cache');
    }

    public function render(string $templateFile, array $params = []): string
    {
        return $this->latte->renderToString(ROOT_DIR . '/app/Views/templates/' . $templateFile, $params);
    }
}
