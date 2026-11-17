<?php

namespace App\Controller;

use App\Entity\ExpenseType;
use App\Repository\ExpenseTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/expense-types')]
#[IsGranted('ROLE_USER')]
class ExpenseTypeController extends AbstractController
{
    // GET : Liste des types de dépenses
    #[Route('', methods: ['GET'])]
    public function index(
        ExpenseTypeRepository $repository
    ): JsonResponse {
        $expenseTypes = $repository->findAll();

        $data = array_map(
            fn (ExpenseType $type) => $this->serializeExpenseType($type),
            $expenseTypes
        );

        return $this->json($data);
    }

    // GET : Détails d'un type de dépense
    #[Route('/{id}', methods: ['GET'])]
    public function show(
        ExpenseType $expenseType
    ): JsonResponse {
        return $this->json(
            $this->serializeExpenseType($expenseType)
        );
    }

    // POST : Créer un type de dépense
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ExpenseTypeRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'JSON invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $name = trim($data['name'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            return $this->json([
                'message' => 'Le nom est obligatoire et ne doit pas dépasser 100 caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($repository->findOneBy(['name' => $name])) {
            return $this->json([
                'message' => 'Ce type de dépense existe déjà.'
            ], Response::HTTP_CONFLICT);
        }

        $expenseType = new ExpenseType();
        $expenseType->setName($name);
        $expenseType->setDescription($data['description'] ?? null);
        $expenseType->setActive($data['active'] ?? true);

        $em->persist($expenseType);
        $em->flush();

        return $this->json([
            'message' => 'Type de dépense créé avec succès.',
            'data' => $this->serializeExpenseType($expenseType)
        ], Response::HTTP_CREATED);
    }

    // PUT : Modifier un type de dépense
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        Request $request,
        ExpenseType $expenseType,
        ExpenseTypeRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'JSON invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);

            if ($name === '' || mb_strlen($name) > 100) {
                return $this->json([
                    'message' => 'Le nom est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $existing = $repository->findOneBy(['name' => $name]);

            if ($existing && $existing->getId() !== $expenseType->getId()) {
                return $this->json([
                    'message' => 'Ce type de dépense existe déjà.'
                ], Response::HTTP_CONFLICT);
            }

            $expenseType->setName($name);
        }

        if (array_key_exists('description', $data)) {
            $expenseType->setDescription($data['description']);
        }

        if (array_key_exists('active', $data)) {
            if (!is_bool($data['active'])) {
                return $this->json([
                    'message' => 'Le champ active doit être un booléen.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $expenseType->setActive($data['active']);
        }

        $em->flush();

        return $this->json([
            'message' => 'Type de dépense modifié avec succès.',
            'data' => $this->serializeExpenseType($expenseType)
        ]);
    }

    // DELETE : Supprimer un type de dépense
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        ExpenseType $expenseType,
        EntityManagerInterface $em
    ): JsonResponse {
        $em->remove($expenseType);
        $em->flush();

        return $this->json([
            'message' => 'Type de dépense supprimé avec succès.'
        ]);
    }

    // Sérialisation des données
    private function serializeExpenseType(
        ExpenseType $expenseType
    ): array {
        return [
            'id' => $expenseType->getId(),
            'name' => $expenseType->getName(),
            'description' => $expenseType->getDescription(),
            'active' => $expenseType->isActive(),
        ];
    }
}

