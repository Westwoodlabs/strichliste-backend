<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UserToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserTokenRepository extends ServiceEntityRepository {

    function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, UserToken::class);
    }

    function findAll($limit = null, $offset = null) {
        return $this->createQueryBuilder('t')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $user
     * @param int $offset
     * @param int $limit
     * @return UserToken[]
     */
    function findByUser(User $user, $limit = null, $offset = null) {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit, $offset);
    }

    function findByToken(string $token): ?UserToken {
        return $this->findOneBy(['token' => $token]);
    }
}
