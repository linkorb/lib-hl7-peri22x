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

    /**
     * @param string $name
     * @param mixed $value
     */
    public function addMetadata($name, $value);

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasMetadata($name);

    /**
     * Get all metadata or a metadata value by name.
     *
     * @param null|string $name
     *
     * @return array|mixed
     */
    public function getMetadata($name = null);
}
