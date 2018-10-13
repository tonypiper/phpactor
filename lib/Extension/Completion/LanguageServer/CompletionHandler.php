<?php

namespace Phpactor\Extension\Completion\LanguageServer;

use Generator;
use LanguageServerProtocol\CompletionItem;
use LanguageServerProtocol\CompletionList;
use LanguageServerProtocol\Diagnostic;
use LanguageServerProtocol\DiagnosticSeverity;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\Range;
use LanguageServerProtocol\TextDocumentItem;
use Microsoft\PhpParser\DiagnosticKind;
use Phpactor\Completion\Core\Completor;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServer\Core\Handler;
use Phpactor\LanguageServer\Core\Session\Manager;
use Phpactor\LanguageServer\Core\Transport\NotificationMessage;
use Phpactor\WorseReflection\Core\Reflector\SourceCodeReflector;

class CompletionHandler implements Handler
{
    /**
     * @var Completor
     */
    private $completor;

    /**
     * @var Manager
     */
    private $sessionManager;

    /**
     * @var SourceCodeReflector
     */
    private $reflector;

    public function __construct(Manager $sessionManager, Completor $completor, SourceCodeReflector $reflector)
    {
        $this->completor = $completor;
        $this->sessionManager = $sessionManager;
        $this->reflector = $reflector;
    }

    public function name(): string
    {
        return 'textDocument/completion';
    }

    public function __invoke(TextDocumentItem $textDocument, Position $position): Generator
    {
        $textDocument = $this->sessionManager->current()->workspace()->get($textDocument->uri);

        $suggestions = $this->completor->complete(
            $textDocument->text,
            $position->toOffset($textDocument->text)
        );

        $completionList = new CompletionList();
        $completionList->isIncomplete = true;

        foreach ($suggestions as $suggestion) {
            /** @var Suggestion $suggestion */
            $completionList->items[] = new CompletionItem(
                $suggestion->name(),
                PhpactorToLspCompletionType::fromPhpactorType($suggestion->type()),
                $suggestion->shortDescription()
            );

        }

        yield $completionList;
    }
}
