<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="`user_token`", uniqueConstraints={
 *     @UniqueConstraint(name="token_unique", columns={"token"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\UserTokenRepository")
 */
class UserToken {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $token = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", fetch="EAGER", inversedBy="tokens")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    function getId(): ?int {
        return $this->id;
    }

    function getToken(): ?string {
        return $this->token;
    }

    function setToken(string $token): self {
        $this->token = $token;

        return $this;
    }
}
