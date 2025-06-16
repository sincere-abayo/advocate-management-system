<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
requireUserType('advocate');

$advocateId = $_SESSION['advocate_id'];
$pageTitle = "Income Records";
include_once '../includes/header.php';

$conn = getDBConnection();
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$selectedCase = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

// Get years for filter
$years = [];
for ($i = $currentYear; $i >= $currentYear - 4; $i--) $years[] = $i;

// Get cases for filter
$casesQuery = "SELECT c.case_id, c.case_number, c.title FROM cases c JOIN case_assignments ca ON c.case_id = ca.case_id WHERE ca.advocate_id = ?";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

// Build income query
$where = "b.advocate_id = ? AND b.status = 'paid'";
$params = [$advocateId];
$types = "i";
if ($selectedYear) {
    $where .= " AND YEAR(b.payment_date) = ?";
    $params[] = $selectedYear;
    $types .= "i";
}
if ($selectedCase) {
    $where .= " AND b.case_id = ?";
    $params[] = $selectedCase;
    $types .= "i";
}
$incomeQuery = "
    SELECT b.billing_id, b.case_id, c.case_number, c.title as case_title, b.amount, b.payment_method, b.payment_date, b.description
    FROM billings b
    LEFT JOIN cases c ON b.case_id = c.case_id
    WHERE $where
    ORDER BY b.payment_date DESC
";
$incomeStmt = $conn->prepare($incomeQuery);
$incomeStmt->bind_param($types, ...$params);
$incomeStmt->execute();
$incomeResult = $incomeStmt->get_result();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Income Records</h1>
    <form method="get" class="mb-4 flex flex-wrap gap-4">
        <select name="year" class="border rounded px-2 py-1">
            <?php foreach ($years as $year): ?>
                <option value="<?= $year ?>" <?= $selectedYear == $year ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
        </select>
        <select name="case_id" class="border rounded px-2 py-1">
            <option value="0">All Cases</option>
            <?php while ($case = $casesResult->fetch_assoc()): ?>
                <option value="<?= $case['case_id'] ?>" <?= $selectedCase == $case['case_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($case['case_number']) ?> - <?= htmlspecialchars($case['title']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="bg-blue-600 text-white px-4 py-1 rounded">Filter</button>
    </form>
    <div class="bg-white rounded shadow p-4">
        <?php if ($incomeResult->num_rows === 0): ?>
            <div class="text-gray-500">No income records found.</div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Case</th>
                        <th class="px-4 py-2 text-left">Amount</th>
                        <th class="px-4 py-2 text-left">Payment Method</th>
                        <th class="px-4 py-2 text-left">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $incomeResult->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-2"><?= date('Y-m-d', strtotime($row['payment_date'])) ?></td>
                            <td class="px-4 py-2">
                                <?php if ($row['case_id']): ?>
                                    <a href="../cases/view.php?id=<?= $row['case_id'] ?>" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($row['case_number']) ?>
                                    </a>
                                    <span class="text-gray-500"><?= htmlspecialchars($row['case_title']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 font-semibold">RWF <?= number_format($row['amount'], 2) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['payment_method'] ?? 'N/A') ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php include_once '../includes/footer.php'; ?>