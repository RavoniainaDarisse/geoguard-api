<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/products')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    // GET : Liste des produits
    #[Route('', methods: ['GET'])]
    public function index(
        ProductRepository $repository
    ): JsonResponse {
        $products = $repository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        $data = array_map(
            fn (Product $product) => $this->serializeProduct($product),
            $products
        );

        return $this->json($data);
    }

    // GET : Détails d'un produit
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json(
            $this->serializeProduct($product)
        );
    }

    // POST : Créer un produit
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        ProductRepository $repository,
        ProductCategoryRepository $categoryRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'JSON invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Nom
        $name = trim($data['name'] ?? '');

        if ($name === '' || mb_strlen($name) > 150) {
            return $this->json([
                'message' => 'Le nom est obligatoire et ne doit pas dépasser 150 caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Référence
        $reference = trim($data['reference'] ?? '');

        if ($reference === '' || mb_strlen($reference) > 100) {
            return $this->json([
                'message' => 'La référence est obligatoire et ne doit pas dépasser 100 caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($repository->findOneBy(['reference' => $reference])) {
            return $this->json([
                'message' => 'Cette référence existe déjà.'
            ], Response::HTTP_CONFLICT);
        }

        // Prix
        $purchasePrice = $data['purchasePrice'] ?? null;
        $sellingPrice = $data['sellingPrice'] ?? null;

        if (
            !$this->isValidPrice($purchasePrice) ||
            !$this->isValidPrice($sellingPrice)
        ) {
            return $this->json([
                'message' => 'Les prix doivent être positifs ou nuls, avec au maximum deux décimales.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Description
        $description = $data['description'] ?? null;

        if ($description !== null && !is_string($description)) {
            return $this->json([
                'message' => 'La description doit être une chaîne de caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Image
        $image = $data['image'] ?? null;

        if ($image !== null && !is_string($image)) {
            return $this->json([
                'message' => 'Le champ image doit être une chaîne de caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Catégorie
        $categoryId = filter_var(
            $data['categoryId'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($categoryId === false || $categoryId === null) {
            return $this->json([
                'message' => 'categoryId est obligatoire et doit être un identifiant valide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $category = $categoryRepository->find($categoryId);

        if (!$category || !$category->isActive()) {
            return $this->json([
                'message' => 'Catégorie introuvable ou inactive.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Statut actif
        $active = $data['active'] ?? true;

        if (!is_bool($active)) {
            return $this->json([
                'message' => 'Le champ active doit être un booléen.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Création
        $product = new Product();
        $product->setName($name);
        $product->setReference($reference);
        $product->setDescription($description);
        $product->setPurchasePrice((string) $purchasePrice);
        $product->setSellingPrice((string) $sellingPrice);
        $product->setImage($image);
        $product->setActive($active);
        $product->setCategory($category);

        $em->persist($product);
        $em->flush();

        return $this->json([
            'message' => 'Produit créé avec succès.',
            'data' => $this->serializeProduct($product)
        ], Response::HTTP_CREATED);
    }

    // PUT : Modifier un produit
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        Request $request,
        Product $product,
        ProductRepository $repository,
        ProductCategoryRepository $categoryRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'JSON invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Nom
        if (array_key_exists('name', $data)) {
            if (
                !is_string($data['name']) ||
                trim($data['name']) === '' ||
                mb_strlen(trim($data['name'])) > 150
            ) {
                return $this->json([
                    'message' => 'Le nom est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setName(trim($data['name']));
        }

        // Référence
        if (array_key_exists('reference', $data)) {
            if (
                !is_string($data['reference']) ||
                trim($data['reference']) === '' ||
                mb_strlen(trim($data['reference'])) > 100
            ) {
                return $this->json([
                    'message' => 'La référence est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $reference = trim($data['reference']);
            $existing = $repository->findOneBy([
                'reference' => $reference
            ]);

            if ($existing && $existing->getId() !== $product->getId()) {
                return $this->json([
                    'message' => 'Cette référence existe déjà.'
                ], Response::HTTP_CONFLICT);
            }

            $product->setReference($reference);
        }

        // Prix d'achat
        if (array_key_exists('purchasePrice', $data)) {
            if (!$this->isValidPrice($data['purchasePrice'])) {
                return $this->json([
                    'message' => 'Le prix d’achat est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setPurchasePrice(
                (string) $data['purchasePrice']
            );
        }

        // Prix de vente
        if (array_key_exists('sellingPrice', $data)) {
            if (!$this->isValidPrice($data['sellingPrice'])) {
                return $this->json([
                    'message' => 'Le prix de vente est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setSellingPrice(
                (string) $data['sellingPrice']
            );
        }

        // Description
        if (array_key_exists('description', $data)) {
            if (
                $data['description'] !== null &&
                !is_string($data['description'])
            ) {
                return $this->json([
                    'message' => 'La description est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setDescription($data['description']);
        }

        // Image
        if (array_key_exists('image', $data)) {
            if (
                $data['image'] !== null &&
                !is_string($data['image'])
            ) {
                return $this->json([
                    'message' => 'Le champ image est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setImage($data['image']);
        }

        // Statut actif
        if (array_key_exists('active', $data)) {
            if (!is_bool($data['active'])) {
                return $this->json([
                    'message' => 'Le champ active doit être un booléen.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setActive($data['active']);
        }

        // Catégorie
        if (array_key_exists('categoryId', $data)) {
            $categoryId = filter_var(
                $data['categoryId'],
                FILTER_VALIDATE_INT
            );

            if ($categoryId === false || $categoryId === null) {
                return $this->json([
                    'message' => 'L’identifiant de catégorie est invalide.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $category = $categoryRepository->find($categoryId);

            if (!$category || !$category->isActive()) {
                return $this->json([
                    'message' => 'Catégorie introuvable ou inactive.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->setCategory($category);
        }

        $em->flush();

        return $this->json([
            'message' => 'Produit modifié avec succès.',
            'data' => $this->serializeProduct($product)
        ]);
    }

    // DELETE : Supprimer un produit
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        Product $product,
        EntityManagerInterface $em
    ): JsonResponse {
        $em->remove($product);
        $em->flush();

        return $this->json([
            'message' => 'Produit supprimé avec succès.'
        ]);
    }

    // Vérification des prix
    private function isValidPrice(mixed $price): bool
    {
        return (
            (is_string($price) || is_int($price) || is_float($price)) &&
            preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $price) === 1
        );
    }

    // Sérialisation
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'reference' => $product->getReference(),
            'description' => $product->getDescription(),
            'purchasePrice' => $product->getPurchasePrice(),
            'sellingPrice' => $product->getSellingPrice(),
            'image' => $product->getImage(),
            'active' => $product->isActive(),
            'createdAt' => $product->getCreatedAt()?->format(DATE_ATOM),
            'category' => [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
            ],
        ];
    }
}
