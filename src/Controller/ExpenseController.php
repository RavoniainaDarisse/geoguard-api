<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\ExpenseType;
use App\Repository\ExpenseRepository;
use App\Repository\ExpenseTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/expenses')]
#[IsGranted('ROLE_USER')]
class ExpenseController extends AbstractController
{
    // GET : Liste des dépenses
    #[Route('', methods: ['GET'])]
    public function index(
        ExpenseRepository $repository
    ): JsonResponse {
        $expenses = $repository->findBy(
            [],
            ['expenseDate' => 'DESC']
        );

        $data = array_map(
            fn (Expense $expense) => $this->serializeExpense($expense),
            $expenses
        );

        return $this->json($data);
    }

    // GET : Détails d'une dépense
    #[Route('/{id}', methods: ['GET'])]
    public function show(Expense $expense): JsonResponse
    {
        return $this->json(
            $this->serializeExpense($expense)
        );
    }

    // POST : Créer une dépense
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ExpenseTypeRepository $typeRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'JSON invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du montant
        $amount = $data['amount'] ?? null;

        if (
            !is_numeric($amount) ||
            !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $amount) ||
            (float) $amount <= 0
        ) {
            return $this->json([
                'message' => 'Le montant doit être positif et comporter au maximum 2 décimales.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation de la description
        $description = $data['description'] ?? null;

        if ($description !== null && !is_string($description)) {
            return $this->json([
                'message' => 'La description doit être une chaîne de caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation de la date
        if (
            !isset($data['expenseDate']) ||
            !is_string($data['expenseDate'])
        ) {
            return $this->json([
                'message' => 'La date de dépense est obligatoire.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $expenseDate = new \DateTimeImmutable($data['expenseDate']);
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'La date de dépense est invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérification du type de dépense
        $typeId = filter_var(
            $data['expenseTypeId'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($typeId === false || $typeId === null) {
            return $this->json([
                'message' => 'expenseTypeId est obligatoire et doit être un identifiant valide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $expenseType = $typeRepository->find($typeId);

        if (!$expenseType || !$expenseType->isActive()) {
            return $this->json([
                'message' => 'Type de dépense introuvable ou inactif.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Création
        $expense = new Expense();
        $expense->setAmount((string) $amount);
        $expense->setDescription($description);
        $expense->setExpenseDate($expenseDate);
        $expense->setExpenseType($expenseType);

        $em->persist($expense);
        $em->flush();

        return $this->json([
            'message' => 'Dépense créée avec succès.',
            'data' => $this->serializeExpense($expense)
        ], Response::HTTP_CREATED);
    }

    // PUT : Modifier une dépense
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        Request $request,
        Expense $expense,
        ExpenseTypeRepository $typeRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'JSON invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Modification du montant
        if (array_key_exists('amount', $data)) {
            $amount = $data['amount'];

            if (
                !is_numeric($amount) ||
                !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $amount) ||
                (float) $amount <= 0
            ) {
                return $this->json([
                    'message' => 'Le montant est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $expense->setAmount((string) $amount);
        }

        // Modification de la description
        if (array_key_exists('description', $data)) {
            if (
                $data['description'] !== null &&
                !is_string($data['description'])
            ) {
                return $this->json([
                    'message' => 'La description est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $expense->setDescription($data['description']);
        }

        // Modification de la date
        if (array_key_exists('expenseDate', $data)) {
            if (!is_string($data['expenseDate'])) {
                return $this->json([
                    'message' => 'La date est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            try {
                $expenseDate = new \DateTimeImmutable($data['expenseDate']);
            } catch (\Exception $e) {
                return $this->json([
                    'message' => 'La date est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $expense->setExpenseDate($expenseDate);
        }

        // Modification du type
        if (array_key_exists('expenseTypeId', $data)) {
            $typeId = filter_var(
                $data['expenseTypeId'],
                FILTER_VALIDATE_INT
            );

            if ($typeId === false || $typeId === null) {
                return $this->json([
                    'message' => 'Identifiant du type invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $expenseType = $typeRepository->find($typeId);

            if (!$expenseType || !$expenseType->isActive()) {
                return $this->json([
                    'message' => 'Type de dépense introuvable ou inactif.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $expense->setExpenseType($expenseType);
        }

        $em->flush();

        return $this->json([
            'message' => 'Dépense modifiée avec succès.',
            'data' => $this->serializeExpense($expense)
        ]);
    }

    // DELETE : Supprimer une dépense
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        Expense $expense,
        EntityManagerInterface $em
    ): JsonResponse {
        $em->remove($expense);
        $em->flush();

        return $this->json([
            'message' => 'Dépense supprimée avec succès.'
        ]);
    }

    // Sérialisation
    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => $expense->getId(),
            'amount' => $expense->getAmount(),
            'description' => $expense->getDescription(),
            'expenseDate' => $expense->getExpenseDate()?->format(DATE_ATOM),
            'expenseType' => [
                'id' => $expense->getExpenseType()->getId(),
                'name' => $expense->getExpenseType()->getName(),
            ],
        ];
    }
}
