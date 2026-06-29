<?php

namespace alcamo\pwa;

use alcamo\xml_conf\AbstractConfDocument;

class ConfDocument extends AbstractConfDocument
{
    /**
     * @brief Return attribute or child element of document element
     *
     * @return Attribute of name $optionName if the type of the document
     * element supports such an attribute (returning `null` if the attribute
     * is supported but absent in the instance document). Otherwise the first
     * child element with local name $optionName in the document namespace.
     */
    public function __get(string $optionName)
    {
        return isset($this->documentElement->getType()->getAttrs()[$optionName])
            ? $this->documentElement->$optionName
            : $this->query("/*/c:$optionName")[0];
    }
}
