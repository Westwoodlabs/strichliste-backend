<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserToken;
use App\Exception\ParameterInvalidException;
use App\Exception\ParameterMissingException;
use App\Exception\TokenAlreadyInUseException;
use App\Exception\UserAlreadyExistsException;
use App\Exception\UserNotFoundException;
use App\Serializer\UserSerializer;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/user")
 */
class UserController extends AbstractController
{

    /**
     * @var UserSerializer
     */
    private $userSerializer;

    function __construct(UserSerializer $userSerializer)
    {
        $this->userSerializer = $userSerializer;
    }

    /**
     * @Route(methods="GET")
     */
    function list(Request $request, UserService $userService, EntityManagerInterface $entityManager)
    {
        $active = $request->query->get('active');

        $staleDateTime = $userService->getStaleDateTime();
        $userRepository = $entityManager->getRepository(User::class);

        if ($active === 'true') {
            $users = $userRepository->findAllActive($staleDateTime);
        } elseif ($active === 'false') {
            $users = $userRepository->findAllInactive($staleDateTime);
        } else {
            $users = $userRepository->findAll();
        }

        usort($users, function (User $a, User $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $this->json([
            'users' => array_map(function (User $user) {
                return $this->userSerializer->serialize($user);
            }, $users)
        ]);
    }

    /**
     * @Route(methods="POST")
     */
    function createUser(Request $request, EntityManagerInterface $entityManager)
    {

        $name = $request->request->get('name');
        if (!$name) {
            throw new ParameterMissingException('name');
        }

        // TODO: Use sanitize-helper
        $name = trim($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        if (!$name || mb_strlen($name) > 64) {
            throw new ParameterInvalidException('name');
        }

        if ($entityManager->getRepository(User::class)->findByName($name)) {
            throw new UserAlreadyExistsException($name);
        }

        $user = new User();
        $user->setName($name);

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                throw new ParameterInvalidException('email');
            }

            $user->setEmail(trim($email));
        }

         $this->updateUserTokens($request, $entityManager, $user);

        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
        // re-read after changing stuff
        $user = $entityManager->getRepository(User::class)->findByIdentifier($user->getId());

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }

    /**
     * @Route("/search", methods="GET")
     */
    function search(Request $request, EntityManagerInterface $entityManager)
    {
        $query = $request->query->get('query');
        $limit = $request->query->get('limit', 25);

        $results = $entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.name LIKE :query')
            ->andWhere('u.disabled = false')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.name')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'count' => count($results),
            'users' => array_map(function (User $user) {
                return $this->userSerializer->serialize($user);
            }, $results),
        ]);
    }

    /**
     * @Route("/token", methods="GET")
     */
    function searchByToken(Request $request, EntityManagerInterface $entityManager)
    {
        $token = $request->query->get('token');
        $userToken = $entityManager->getRepository(UserToken::class)->findByToken($token);
        if (!$userToken) {
            throw new UserNotFoundException($token);
        }

        return $this->json([
            'user' => $this->userSerializer->serialize($userToken->getUser()),
        ]);
    }

    /**
     * @Route("/{userId}", methods="GET")
     */
    function user($userId, EntityManagerInterface $entityManager)
    {
        $user = $entityManager->getRepository(User::class)->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }

    /**
     * @Route("/{userId}", methods="POST")
     */
    function updateUser($userId, Request $request, EntityManagerInterface $entityManager)
    {
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $name = $request->request->get('name');
        if (mb_strlen($name) > 64) {
            throw new ParameterInvalidException('name');
        }

        if ($name) {
            $name = trim($name);
            $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

            if ($name !== $user->getName() && $entityManager->getRepository(User::class)->findByName($name)) {
                throw new UserAlreadyExistsException($name);
            }

            $user->setName($name);
        }

        $email = $request->request->get('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                throw new ParameterInvalidException('email');
            }

            $user->setEmail($email);
        }
        $this->updateUserTokens($request, $entityManager, $user);

        $isDisabled = $request->request->get('isDisabled');
        if ($isDisabled !== null) {
            $user->setDisabled($isDisabled);
        }

        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
        // re-read after changing stuff
        $user = $entityManager->getRepository(User::class)->findByIdentifier($userId);

        return $this->json([
            'user' => $this->userSerializer->serialize($user),
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param User $user
     * @return void
     * @throws TokenAlreadyInUseException
     */
    public function updateUserTokens(Request $request, EntityManagerInterface $entityManager, User $user): void
    {
        /** @var array $tokens
         */
        $tokens = $request->request->get('tokens');
        if ($tokens !== null) {
            foreach ($tokens as $token) {
                $userToken = $entityManager->getRepository(UserToken::class)->findByToken($token);
                if ($userToken && $userToken->getUser() != $user) {
                    throw new TokenAlreadyInUseException($token);
                }
                if (!$user->getTokens()->exists(function ($key, $element) use ($token) {
                    return $element->getToken() === $token;
                })) {
                    $addToken = new UserToken();
                    $addToken->setToken($token);
                    $addToken->setUser($user);
                    $entityManager->persist($addToken);
                }
            }
            foreach ($user->getTokens() as $userToken) {
                if (!in_array($userToken->getToken(), $tokens)) {
                    $entityManager->remove($userToken);
                }
            }
        }
    }
}
