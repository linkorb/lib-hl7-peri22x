<?php

namespace Hl7Peri22x\Dossier;

use Peri22x\Resource\Resource;

/**
 * Interface of a Dossier.
 */
interface DossierInterface
{
    const RESOURCE_TYPE = 'hub/dossier';

    /**
     * @return \Hl7Peri22x\Document\DocumentInterface;
     */
    public function toXmlDocument();

    /**
     * @return \Hl7Peri22x\Document\DocumentInterface[];
     */
    public function getEmbeddedFiles();

    /**
     * @param \Peri22x\Resource\Resource $resource
     */
    public function setResource(Resource $resource);

    /**
     * @return \Peri22x\Resource\Resource $resource
     */
    public function getResource();

    /**
     * @param string $data
     */
    public function addFileData($data);
}
