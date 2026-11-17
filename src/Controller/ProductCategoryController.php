<?php

namespace App\Controller;

use App\Entity\ProductCategory;
use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/product-categories')]
#[IsGranted('ROLE_USER')]
class ProductCategoryController extends AbstractController
{
    // Liste des catégories
    #[Route('', name: 'api_product_category_index', methods: ['GET'])]
    public function index(
        ProductCategoryRepository $repository
    ): JsonResponse {
        $categories = $repository->findBy([], ['id' => 'DESC']);

        $data = array_map(
            fn(ProductCategory $category) => $this->serializeCategory($category),
            $categories
        );

        return $this->json([
            'message' => 'Liste des catégories.',
            'data' => $data,
        ]);
    }

    // Détail d'une catégorie
    #[Route('/{id}', name: 'api_product_category_show', methods: ['GET'])]
    public function show(ProductCategory $category): JsonResponse
    {
        return $this->json([
            'data' => $this->serializeCategory($category),
        ]);
    }

    // Création d'une catégorie
    #[Route('', name: 'api_product_category_create', methods: ['POST'])]
    public function create(
        Request $request,
        ProductCategoryRepository $repository,
        EntityManagerInterface $entityManager
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

        $name = trim($data['name'] ?? '');

        if (!is_string($name) || $name === '' || mb_strlen($name) > 100) {
            return $this->json([
                'message' => 'Le nom est obligatoire (100 caractères maximum).',
            ], 400);
        }

        if ($repository->findOneBy(['name' => $name])) {
            return $this->json([
                'message' => 'Cette catégorie existe déjà.',
            ], 409);
        }

        $category = new ProductCategory();
        $category->setName($name);
        $category->setDescription($data['description'] ?? null);
        $category->setActive($data['active'] ?? true);

        $entityManager->persist($category);
        $entityManager->flush();

        return $this->json([
            'message' => 'Catégorie créée avec succès.',
            'data' => $this->serializeCategory($category),
        ], Response::HTTP_CREATED);
    }

    // Modification d'une catégorie
    #[Route('/{id}', name: 'api_product_category_update', methods: ['PUT'])]
    public function update(
        ProductCategory $category,
        Request $request,
        ProductCategoryRepository $repository,
        EntityManagerInterface $entityManager
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

        if (array_key_exists('name', $data)) {
            if (!is_string($data['name'])) {
                return $this->json(['message' => 'Nom invalide.'], 400);
            }

            $name = trim($data['name']);

            if ($name === '' || mb_strlen($name) > 100) {
                return $this->json(['message' => 'Nom invalide.'], 400);
            }

            $existing = $repository->findOneBy(['name' => $name]);

            if ($existing && $existing->getId() !== $category->getId()) {
                return $this->json([
                    'message' => 'Cette catégorie existe déjà.',
                ], 409);
            }

            $category->setName($name);
        }

        if (array_key_exists('description', $data)) {
            if ($data['description'] !== null && !is_string($data['description'])) {
                return $this->json(['message' => 'Description invalide.'], 400);
            }

            $category->setDescription($data['description']);
        }

        if (array_key_exists('active', $data)) {
            if (!is_bool($data['active'])) {
                return $this->json(['message' => 'Valeur active invalide.'], 400);
            }

            $category->setActive($data['active']);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Catégorie modifiée avec succès.',
            'data' => $this->serializeCategory($category),
        ]);
    }

    // Suppression d'une catégorie
    #[Route('/{id}', name: 'api_product_category_delete', methods: ['DELETE'])]
    public function delete(
        ProductCategory $category,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $entityManager->remove($category);
        $entityManager->flush();

        return $this->json([
            'message' => 'Catégorie supprimée avec succès.',
        ]);
    }

    // Format JSON commun
    private function serializeCategory(ProductCategory $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'active' => $category->isActive(),
        ];
    }
}
