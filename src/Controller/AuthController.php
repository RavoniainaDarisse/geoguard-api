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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

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
            return $this->json(['message' => 'JSON invalide.'], 400);
        }

        if (!is_array($data)) {
            return $this->json(['message' => 'Données invalides.'], 400);
        }

        $nom = trim($data['nom'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($nom === '' || mb_strlen($nom) > 100) {
            return $this->json([
                'message' => 'Le nom est obligatoire (100 caractères maximum).'
            ], 400);
        }

        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Email invalide.'], 400);
        }

        if (!is_string($password) || strlen($password) < 8) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
            ], 400);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'message' => 'Cette adresse email est déjà utilisée.'
            ], 409);
        }

        $user = new User();
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );
        $user->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'Inscription réussie.',
            'user' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }


    #[Route('/api/v1/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
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
                'message' => 'JSON invalide.'
            ], 400);
        }

        if (!is_array($data)) {
            return $this->json([
                'message' => 'Données invalides.'
            ], 400);
        }

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!is_string($email) || !is_string($password) || $email === '' || $password === '') {
            return $this->json([
                'message' => 'Email et mot de passe obligatoires.'
            ], 400);
        }

        $user = $userRepository->findOneBy([
            'email' => $email
        ]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'message' => 'Email ou mot de passe incorrect.'
            ], 401);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'message' => 'Connexion réussie.',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles()
            ]
        ]);
    }


    #[Route('/api/v1/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        return $this->json([
            'message' => 'Déconnexion réussie.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ],
        ]);
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
                'nom' => $user->getNom(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
