<?php

namespace App\Services\FiscalExtraction\Sri;

use RuntimeException;

final class JsfViewState
{
    public function __construct(public readonly string $viewState)
    {
    }

    public static function fromHtml(string $html): self
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($document);
        $node = $xpath->query('//input[@name="javax.faces.ViewState"]')->item(0);

        if ($node === null) {
            throw new RuntimeException('javax.faces.ViewState was not found on the SRI page: the session may have expired.');
        }

        return new self($node->getAttribute('value'));
    }
}