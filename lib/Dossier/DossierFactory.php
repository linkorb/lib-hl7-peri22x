<?php

namespace Hl7Peri22x\Dossier;

use Hl7Peri22x\Document\DocumentFactory;
use Peri22x\Attachment\AttachmentFactory;

class DossierFactory
{
    /**
     * @var \Peri22x\Attachment\AttachmentFactory
     */
    private $attachmentFactory;
    /**
     * @var \Hl7Peri22x\Document\DocumentFactory
     */
    private $documentFactory;

    /**
     * @param \Peri22x\Attachment\AttachmentFactory $attachmentFactory
     * @param \Hl7Peri22x\Document\DocumentFactory $documentFactory
     */
    public function __construct(
        AttachmentFactory $attachmentFactory,
        DocumentFactory $documentFactory
    ) {
        $this->attachmentFactory = $attachmentFactory;
        $this->documentFactory = $documentFactory;
    }

    /**
     * Create a Dossier.
     *
     * @return \Hl7Peri22x\Dossier\Dossier
     */
    public function create()
    {
        return new Dossier($this->attachmentFactory, $this->documentFactory);
    }
}
