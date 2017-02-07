<?php

namespace SkyDiablo\AWSBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SkyDiabloAWSBundle extends Bundle
{

    /**
     * @return null|\Symfony\Component\DependencyInjection\Extension\ExtensionInterface
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = $this->createContainerExtension();
        }
        return $this->extension;
    }


}
