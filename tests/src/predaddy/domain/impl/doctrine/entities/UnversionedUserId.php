<?php

namespace predaddy\domain\impl\doctrine\entities;

use predaddy\domain\UUIDAggregateId;

/**
 * Class UserId
 *
 * @package src\predaddy\domain\impl\doctrine\entities
 * @author Janos Szurovecz <szjani@szjani.hu>
 */
final class UnversionedUserId extends UUIDAggregateId
{

    /**
     * @return string FQCN
     */
    public function aggregateClass()
    {
        return UnversionedUser::className();
    }
}
