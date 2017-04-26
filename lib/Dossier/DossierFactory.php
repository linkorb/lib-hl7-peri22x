<?php

namespace Hl7Peri22x\Dossier;

use Hl7Peri22x\Document\DocumentFactory;

class DossierFactory
{
    /**
     * @var \Hl7Peri22x\Document\DocumentFactory
     */
    private $documentFactory;

    /**
     * @param \Hl7Peri22x\Document\DocumentFactory $documentFactory
     */
    public function __construct(DocumentFactory $documentFactory)
    {
        $this->documentFactory = $documentFactory;
    }

    /**
     * Create a Dossier.
     *
     * @return \Hl7Peri22x\Dossier\Dossier
     */
    public function create()
    {
        return new Dossier($this->documentFactory);
    }
}
