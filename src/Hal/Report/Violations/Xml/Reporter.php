<?php

/** @noinspection PhpComposerExtensionStubsInspection As ext-dom is not required but suggested. */
declare(strict_types=1);

namespace Hal\Report\Violations\Xml;

use DOMDocument;
use DOMElement;
use DOMException;
use Hal\Application\Config\ConfigBagInterface;
use Hal\Component\Output\Output;
use Hal\Metric\Metrics;
use Hal\Report\ReporterInterface;
use Hal\Violation\Violation;
use function array_map;
use function date;
use function dirname;
use function file_exists;
use function file_put_contents;
use function mkdir;
use function sprintf;

/**
 * This class is responsible for the report of violations on XML file.
 */
final class Reporter implements ReporterInterface
{
    private DOMDocument $xml;

    public function __construct(
        private readonly ConfigBagInterface $config,
        private readonly Output $output
    ) {
        $this->xml = new DOMDocument('1.0', 'UTF-8');
        $this->xml->formatOutput = true;
    }

    /**
     * {@inheritDoc}
     * @throws DOMException
     */
    public function generate(Metrics $metrics): void
    {
        /** @var false|string $logFile */
        $logFile = $this->config->get('report-violations');
        if (false === $logFile) {
            return;
        }

        $root = $this->xml->createElement('pmd');
        $root->setAttribute('version', '@package_version@');
        $root->setAttribute('timestamp', date('c'));

        foreach ($metrics->all() as $metric) {
            /** @var array<Violation> $violations */
            $violations = $metric->get('violations');
            if ([] === $violations) {
                continue;
            }

            $node = $this->xml->createElement('file');
            /** @var string $name */
            $name = $metric->get('name');
            $node->setAttribute('name', $name);
            array_map(function (Violation $violation) use ($node): void {
                $node->appendChild($this->createXmlViolationItem($violation));
            }, $violations);
            $root->appendChild($node);
        }

        $this->xml->appendChild($root);

        // Save XML file.
        file_exists(dirname($logFile)) || mkdir(dirname($logFile), 0o755, true);
        file_put_contents($logFile, $this->xml->saveXML());

        $this->output->writeln(sprintf('XML report generated in "%s"', $logFile));
    }

    /**
     * Creates an XML element representing the given violation.
     *
     * @param Violation $violation
     * @return DOMElement
     * @throws DOMException
     */
    private function createXmlViolationItem(Violation $violation): DOMElement
    {
        $violationName = $violation->getName();
        $item = $this->xml->createElement('violation');
        $item->setAttribute('beginLine', '1');
        $item->setAttribute('rule', $violationName);
        $item->setAttribute('ruleset', $violationName);
        $item->setAttribute('externalInfoUrl', 'http://www.phpmetrics.org/documentation/index.html');
        $item->setAttribute('priority', (string)(4 - $violation->getLevel())); // Priority = reversed level.
        $item->nodeValue = $violation->getDescription();

        return $item;
    }
}
