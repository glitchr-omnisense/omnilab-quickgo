<?php

namespace Omnilab\QuickGo;

use Omnilab\Model\Identifier;
use Omnilab\Model\Scheme;

/**
 * One GO annotation of a gene product, as QuickGO gives it: the term, its
 * aspect, the qualifier (enables, involved_in, located_in, part_of,
 * contributes_to, colocalizes_with...; "NOT|" before it for a negative
 * annotation), the evidence (the GO code, IDA; and the ECO term), the
 * reference it rests on (PMID:19740759, GO_REF:0000107), the group that
 * made it and when.
 */
final readonly class Annotation
{
    public function __construct(
        public Identifier $term,
        public ?string $name,
        public string $aspect,
        public string $qualifier,
        public ?string $evidence,
        public ?string $eco,
        public ?string $reference,
        public ?string $assignedBy,
        public ?\DateTimeImmutable $date,
    ) {
    }

    /** @param array<string, mixed> $row a result of annotation/search */
    public static function fromRow(array $row): ?self
    {
        $term = Identifier::tryOf(Scheme::GO, $row['goId'] ?? null);
        if (null === $term || !\is_string($row['goAspect'] ?? null)) {
            return null;
        }
        $date = \is_string($row['date'] ?? null) ? \DateTimeImmutable::createFromFormat('!Ymd', $row['date'], new \DateTimeZone('UTC')) : false;

        return new self(
            $term,
            self::string($row['goName'] ?? null),
            $row['goAspect'],
            self::string($row['qualifier'] ?? null) ?? '',
            self::string($row['goEvidence'] ?? null),
            self::string($row['evidenceCode'] ?? null),
            self::string($row['reference'] ?? null),
            self::string($row['assignedBy'] ?? null),
            false === $date ? null : $date,
        );
    }

    /** A negative annotation: the product does NOT do, take part in or sit in the term. */
    public function isNegative(): bool
    {
        return str_starts_with($this->qualifier, 'NOT');
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
