<section class="p-6 bg-white dark:bg-gray-800/50 rounded-lg shadow">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        <?= htmlspecialchars((string) ($title ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <p class="mt-2 text-gray-500 dark:text-gray-400">
        <?= htmlspecialchars((string) ($description ?? 'ตัวอย่างหน้า body ในโฟลเดอร์ admin'), ENT_QUOTES, 'UTF-8') ?>
    </p>
</section>
