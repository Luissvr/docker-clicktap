<?php

namespace App\Support;

trait UsesCatalogConnection
{
    public function getConnectionName(): ?string
    {
        return $this->connection ?? CatalogConnection::name();
    }
}
