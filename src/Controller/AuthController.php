<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    /**
     * Inscription d'un nouvel utilisateur.
     */
    #[Route('/api/v1/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            $data = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return $this->json([
                'message' => 'JSON invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($data)) {
            return $this->json([
                'message' => 'Données invalides.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'message' => 'Adresse email invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!is_string($password) || strlen($password) < 8) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir au moins 8 caractères.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'message' => 'Cette adresse email est déjà utilisée.',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );

        // Le client ne peut pas choisir son propre rôle.
        $user->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'Inscription réussie.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Récupération du profil de l'utilisateur connecté.
     */
    #[Route('/api/v1/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'Utilisateur non authentifié.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
