<?php

namespace App\Filament\Resources\PaginaResource\Pages\Concerns;

use App\Support\PagePreviewCompiler;

trait LoadsPagePreview
{
    public ?string $compiledPagePreviewHtml = null;

    public function loadPagePreview(): void
    {
        $this->compiledPagePreviewHtml = PagePreviewCompiler::compile(
            $this->data ?? [],
            $this->form->getRecord(),
        );
    }

    public function renderFinalPagePreview(): string
    {
        return PagePreviewCompiler::compile(
            $this->data ?? [],
            $this->form->getRecord(),
            standalone: true,
        );
    }
}
