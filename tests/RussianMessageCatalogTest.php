<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;
use Respect\Validation\Message\Template;
use SplFileInfo;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

#[Test]
#[CoversNothing]
final class RussianMessageCatalogTest
{
    public function catalogIsNotEmpty(): void
    {
        Assert::true(count($this->loadCatalog()) > 300);
    }

    public function everyCatalogKeyMatchesLiveRespectTemplate(): void
    {
        $liveTemplates = $this->collectLiveTemplates();

        $stale = array_diff(array_keys($this->loadCatalog()), $liveTemplates);

        Assert::same(array_values($stale), []);
    }

    public function everyTranslationKeepsPlaceholdersOfItsKey(): void
    {
        $mismatched = [];
        foreach ($this->loadCatalog() as $key => $translation) {
            if ($this->extractPlaceholders($key) !== $this->extractPlaceholders($translation)) {
                $mismatched[] = $key;
            }
        }

        Assert::same($mismatched, []);
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalog(): array
    {
        return require dirname(__DIR__) . '/messages/ru/yii3-respect-validation.php';
    }

    /**
     * @return list<string>
     */
    private function collectLiveTemplates(): array
    {
        $dir = dirname(__DIR__) . '/vendor/respect/validation/src/Validators';
        $templates = [];

        /** @var SplFileInfo $file */
        foreach (new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)), '/\.php$/') as $file) {
            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = 'Respect\\Validation\\Validators\\' . str_replace('/', '\\', $relative);

            if (!class_exists($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getAttributes(Template::class) as $attribute) {
                $template = $attribute->newInstance();
                $templates[] = $template->default;
                $templates[] = $template->inverted;
            }
        }

        return $templates;
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $template): array
    {
        preg_match_all('/\{\{[^}]+\}\}/', $template, $matches);
        $placeholders = array_unique($matches[0]);
        sort($placeholders);

        return $placeholders;
    }
}
