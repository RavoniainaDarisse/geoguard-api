<?php

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/users', name: 'api_v1_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    // Liste des utilisateurs
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        $data = array_map(fn(User $user) => [
            'id' => $user->getId(),
            'nom' => $user->getNom(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], $users);

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // Détail d'un utilisateur
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    // Créer un utilisateur
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (
            empty($data['nom']) ||
            empty($data['email']) ||
            empty($data['password'])
        ) {
            return $this->json([
                'success' => false,
                'message' => 'Nom, email et mot de passe obligatoires.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy([
            'email' => $data['email']
        ])) {
            return $this->json([
                'success' => false,
                'message' => 'Cet email existe déjà.',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setNom($data['nom']);
        $user->setEmail($data['email']);

        $roles = $data['roles'] ?? ['ROLE_USER'];

        try {
            $user->setRoles($roles);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $this->passwordHasher
            ->hashPassword($user, $data['password']);

        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès.',
            'data' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    // Modifier un utilisateur
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        User $user,
        Request $request
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['nom'])) {
            $user->setNom($data['nom']);
        }

        if (isset($data['email'])) {
            $existingUser = $this->userRepository->findOneBy([
                'email' => $data['email']
            ]);

            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé.',
                ], Response::HTTP_CONFLICT);
            }

            $user->setEmail($data['email']);
        }

        if (isset($data['password']) && $data['password'] !== '') {
            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $data['password']
                )
            );
        }

        if (isset($data['roles'])) {
            try {
                $user->setRoles($data['roles']);
            } catch (\InvalidArgumentException $e) {
                return $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur modifié avec succès.',
        ]);
    }

    // Supprimer un utilisateur
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        if ($this->getUser() === $user) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer votre propre compte.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }
}
