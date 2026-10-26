<?php

declare(strict_types=1);

/**
 * Описывает одну банковскую транзакцию.
 */
class Transaction
{
    /**
     * Создаёт объект банковской транзакции.
     */
    public function __construct(
        private int $id,
        private string $date,
        private float $amount,
        private string $description,
        private string $merchant
    ) {
    }

    /** Возвращает идентификатор транзакции. */
    public function getId(): int
    {
        return $this->id;
    }

    /** Возвращает дату транзакции. */
    public function getDate(): string
    {
        return $this->date;
    }

    /** Возвращает сумму транзакции. */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /** Возвращает описание транзакции. */
    public function getDescription(): string
    {
        return $this->description;
    }

    /** Возвращает получателя платежа. */
    public function getMerchant(): string
    {
        return $this->merchant;
    }

    /** Возвращает количество дней с даты транзакции до текущего дня. */
    public function getDaysSinceTransaction(): int
    {
        $transactionDate = new DateTime($this->date);
        $today = new DateTime('today');

        return (int) $transactionDate->diff($today)->format('%a');
    }
}

/**
 * Определяет общий контракт хранилища банковских транзакций.
 */
interface TransactionStorageInterface
{
    /** Добавляет транзакцию в хранилище. */
    public function addTransaction(Transaction $transaction): void;

    /** Удаляет транзакцию по идентификатору. */
    public function removeTransactionById(int $id): void;

    /**
     * Возвращает все транзакции.
     *
     * @return Transaction[]
     */
    public function getAllTransactions(): array;

    /** Находит транзакцию по идентификатору. */
    public function findById(int $id): ?Transaction;
}

/**
 * Хранит коллекцию транзакций и выполняет базовые операции доступа.
 */
class TransactionRepository implements TransactionStorageInterface
{
    /** @var Transaction[] */
    private array $transactions = [];

    /** Добавляет транзакцию в коллекцию. */
    public function addTransaction(Transaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }

    /** Удаляет транзакцию с указанным ID. */
    public function removeTransactionById(int $id): void
    {
        foreach ($this->transactions as $key => $transaction) {
            if ($transaction->getId() === $id) {
                unset($this->transactions[$key]);
                $this->transactions = array_values($this->transactions);

                return;
            }
        }
    }

    /**
     * Возвращает полный список транзакций.
     *
     * @return Transaction[]
     */
    public function getAllTransactions(): array
    {
        return $this->transactions;
    }

    /** Находит транзакцию по ID или возвращает null. */
    public function findById(int $id): ?Transaction
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->getId() === $id) {
                return $transaction;
            }
        }

        return null;
    }
}

/**
 * Выполняет вычисления и сортировку, используя хранилище транзакций.
 */
class TransactionManager
{
    /** Получает хранилище через интерфейс. */
    public function __construct(
        private TransactionStorageInterface $repository
    ) {
    }

    /** Вычисляет общую сумму всех транзакций. */
    public function calculateTotalAmount(): float
    {
        $total = 0.0;

        foreach ($this->repository->getAllTransactions() as $transaction) {
            $total += $transaction->getAmount();
        }

        return $total;
    }

    /** Вычисляет сумму транзакций в заданном диапазоне дат включительно. */
    public function calculateTotalAmountByDateRange(
        string $startDate,
        string $endDate
    ): float {
        $total = 0.0;

        foreach ($this->repository->getAllTransactions() as $transaction) {
            $date = $transaction->getDate();

            if ($date >= $startDate && $date <= $endDate) {
                $total += $transaction->getAmount();
            }
        }

        return $total;
    }

    /** Подсчитывает количество транзакций указанного получателя. */
    public function countTransactionsByMerchant(string $merchant): int
    {
        $count = 0;

        foreach ($this->repository->getAllTransactions() as $transaction) {
            if (strcasecmp($transaction->getMerchant(), $merchant) === 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Возвращает транзакции, отсортированные по дате по возрастанию.
     *
     * @return Transaction[]
     */
    public function sortTransactionsByDate(): array
    {
        $transactions = $this->repository->getAllTransactions();

        usort(
            $transactions,
            static function (Transaction $a, Transaction $b): int {
                return strcmp($a->getDate(), $b->getDate());
            }
        );

        return $transactions;
    }

    /**
     * Возвращает транзакции, отсортированные по сумме по убыванию.
     *
     * @return Transaction[]
     */
    public function sortTransactionsByAmountDesc(): array
    {
        $transactions = $this->repository->getAllTransactions();

        usort(
            $transactions,
            static function (Transaction $a, Transaction $b): int {
                return $b->getAmount() <=> $a->getAmount();
            }
        );

        return $transactions;
    }
}

/**
 * Формирует HTML-таблицу с банковскими транзакциями.
 */
final class TransactionTableRenderer
{
    /**
     * Формирует и возвращает HTML-таблицу.
     *
     * @param Transaction[] $transactions
     */
    public function render(array $transactions): string
    {
        $html = '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>ID</th>';
        $html .= '<th>Дата</th>';
        $html .= '<th>Сумма</th>';
        $html .= '<th>Описание</th>';
        $html .= '<th>Получатель</th>';
        $html .= '<th>Категория</th>';
        $html .= '<th>Дней прошло</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($transactions as $transaction) {
            $html .= '<tr>';
            $html .= '<td>' . $transaction->getId() . '</td>';
            $html .= '<td>' . htmlspecialchars($transaction->getDate()) . '</td>';
            $html .= '<td>' . number_format($transaction->getAmount(), 2) . '</td>';
            $html .= '<td>' . htmlspecialchars($transaction->getDescription()) . '</td>';
            $html .= '<td>' . htmlspecialchars($transaction->getMerchant()) . '</td>';
            $html .= '<td>' . htmlspecialchars(
                $this->getMerchantCategory($transaction->getMerchant())
            ) . '</td>';
            $html .= '<td>' . $transaction->getDaysSinceTransaction() . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /** Определяет категорию получателя для столбца таблицы. */
    private function getMerchantCategory(string $merchant): string
    {
        return match ($merchant) {
            'SuperMart', 'Green Market' => 'Продукты',
            'Local Restaurant', 'Coffee Point' => 'Питание',
            'TechStore', 'TechMarket' => 'Электроника',
            'BookShop' => 'Книги',
            'NetProvider' => 'Услуги',
            'Pharmacy Plus' => 'Здоровье',
            'TravelBus' => 'Транспорт',
            'Cinema City' => 'Развлечения',
            'SportLand' => 'Спорт',
            default => 'Другое',
        };
    }
}

$repository = new TransactionRepository();

$initialTransactions = [
    new Transaction(1, '2026-01-15', 100.00, 'Payment for groceries', 'SuperMart'),
    new Transaction(2, '2026-02-10', 75.50, 'Dinner with friends', 'Local Restaurant'),
    new Transaction(3, '2026-03-05', 250.00, 'New headphones', 'TechStore'),
    new Transaction(4, '2026-04-12', 40.25, 'Books and stationery', 'BookShop'),
    new Transaction(5, '2026-05-20', 125.99, 'Monthly internet payment', 'NetProvider'),
    new Transaction(6, '2026-06-08', 32.40, 'Medicine purchase', 'Pharmacy Plus'),
    new Transaction(7, '2026-07-17', 18.75, 'Coffee and dessert', 'Coffee Point'),
    new Transaction(8, '2026-08-22', 55.00, 'Bus tickets', 'TravelBus'),
    new Transaction(9, '2026-09-14', 85.00, 'Movie tickets', 'Cinema City'),
    new Transaction(10, '2026-10-03', 199.90, 'Sports equipment', 'SportLand'),
];

foreach ($initialTransactions as $transaction) {
    $repository->addTransaction($transaction);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id = (int) ($_POST['id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $merchant = trim($_POST['merchant'] ?? '');

        if (
            $id > 0
            && $date !== ''
            && $amount >= 0
            && $description !== ''
            && $merchant !== ''
        ) {
            if ($repository->findById($id) === null) {
                $repository->addTransaction(
                    new Transaction($id, $date, $amount, $description, $merchant)
                );
                $message = 'Транзакция добавлена.';
            } else {
                $message = 'Транзакция с таким ID уже существует.';
            }
        } else {
            $message = 'Заполните все поля корректно.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($repository->findById($id) !== null) {
            $repository->removeTransactionById($id);
            $message = 'Транзакция удалена.';
        } else {
            $message = 'Транзакция с таким ID не найдена.';
        }
    }
}

$manager = new TransactionManager($repository);
$renderer = new TransactionTableRenderer();

$sort = $_GET['sort'] ?? '';

$displayTransactions = match ($sort) {
    'date' => $manager->sortTransactionsByDate(),
    'amount_desc' => $manager->sortTransactionsByAmountDesc(),
    default => $repository->getAllTransactions(),
};

$searchResult = null;
$searchPerformed = false;

if (isset($_GET['search_id']) && $_GET['search_id'] !== '') {
    $searchPerformed = true;
    $searchResult = $repository->findById((int) $_GET['search_id']);
}

$dateRangeResult = null;

if (
    isset($_GET['start_date'], $_GET['end_date'])
    && $_GET['start_date'] !== ''
    && $_GET['end_date'] !== ''
) {
    $dateRangeResult = $manager->calculateTotalAmountByDateRange(
        $_GET['start_date'],
        $_GET['end_date']
    );
}

$merchantCountResult = null;
$merchantForCount = trim($_GET['merchant'] ?? '');

if ($merchantForCount !== '') {
    $merchantCountResult = $manager->countTransactionsByMerchant($merchantForCount);
}

$totalAmount = $manager->calculateTotalAmount();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лабораторная работа №5</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            color: #222;
        }

        header,
        footer {
            background: #222;
            color: white;
            padding: 18px 24px;
        }

        main {
            padding: 18px;
        }

        section {
            background: white;
            margin-bottom: 16px;
            padding: 18px;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            border: 1px solid #bbb;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #ececec;
        }

        form {
            margin: 10px 0;
        }

        input,
        select,
        button {
            padding: 7px;
            margin: 3px;
        }

        .message {
            font-weight: bold;
        }

        .result {
            margin-top: 8px;
            padding: 8px;
            background: #f7f7f7;
            border-left: 4px solid #444;
        }

        .forms {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .forms > div {
            min-width: 280px;
        }
    </style>
</head>
<body>
<header>
    <h1>Лабораторная работа №5</h1>
    <p>Объектно-ориентированное программирование в PHP</p>
</header>

<main>
    <section>
        <h2>Банковские транзакции</h2>

        <?php if ($message !== ''): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="get">
            <label for="sort">Сортировка:</label>
            <select name="sort" id="sort">
                <option value="">Без сортировки</option>
                <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>
                    По дате
                </option>
                <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>
                    По сумме по убыванию
                </option>
            </select>
            <button type="submit">Применить</button>
        </form>

        <?php echo $renderer->render($displayTransactions); ?>

        <p><strong>Общая сумма:</strong> <?= number_format($totalAmount, 2) ?></p>
    </section>

    <section>
        <h2>Добавление и удаление</h2>

        <div class="forms">
            <div>
                <h3>Добавить транзакцию</h3>
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <input type="number" name="id" placeholder="ID" min="1" required>
                    <input type="date" name="date" required>
                    <input type="number" name="amount" placeholder="Сумма" step="0.01" min="0" required>
                    <input type="text" name="description" placeholder="Описание" required>
                    <input type="text" name="merchant" placeholder="Получатель" required>
                    <button type="submit">Добавить</button>
                </form>
            </div>

            <div>
                <h3>Удалить транзакцию</h3>
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="number" name="id" placeholder="ID" min="1" required>
                    <button type="submit">Удалить</button>
                </form>
            </div>
        </div>
    </section>

    <section>
        <h2>Поиск и вычисления</h2>

        <div class="forms">
            <div>
                <h3>Поиск по ID</h3>
                <form method="get">
                    <input type="number" name="search_id" placeholder="ID" min="1" required>
                    <button type="submit">Найти</button>
                </form>

                <?php if ($searchPerformed): ?>
                    <div class="result">
                        <?php if ($searchResult instanceof Transaction): ?>
                            Найдено:
                            <?= htmlspecialchars($searchResult->getDescription()) ?>
                            —
                            <?= number_format($searchResult->getAmount(), 2) ?>
                        <?php else: ?>
                            Транзакция не найдена.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h3>Сумма за период</h3>
                <form method="get">
                    <input type="date" name="start_date" required>
                    <input type="date" name="end_date" required>
                    <button type="submit">Посчитать</button>
                </form>

                <?php if ($dateRangeResult !== null): ?>
                    <div class="result">
                        Сумма за период:
                        <?= number_format($dateRangeResult, 2) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h3>Количество по получателю</h3>
                <form method="get">
                    <input type="text" name="merchant" placeholder="Например: TechStore" required>
                    <button type="submit">Посчитать</button>
                </form>

                <?php if ($merchantCountResult !== null): ?>
                    <div class="result">
                        Получатель:
                        <?= htmlspecialchars($merchantForCount) ?><br>
                        Количество транзакций:
                        <?= $merchantCountResult ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<footer>
    Rotari Artemii, I2502(RU)
</footer>
</body>
</html>
