<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="application-name" content="CyberApps">
    <meta name="apple-mobile-web-app-title" content="CyberApps">
    <meta name="theme-color" content="#1643a3">
    <meta name="msapplication-navbutton-color" content="#1643a3">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($title ?? 'CyberRestAPI'), ENT_QUOTES, 'UTF-8') ?> - Fast & Simple Rest Api Framework</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f8fafc;
        }
        .bg-dots-darker {
            background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(0,0,0,0.07)'/%3E%3C/svg%3E");
        }
        @media (prefers-color-scheme: dark) {
            body { background-color: #0f172a; }
            .bg-dots-darker {
                background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(255,255,255,0.07)'/%3E%3C/svg%3E");
            }
        }
    </style>
</head>
<body class="antialiased">
    <div class="relative min-h-screen bg-dots-darker bg-center bg-gray-100 dark:bg-gray-900 selection:bg-red-500 selection:text-white">
        <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-center">
            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <?php include __DIR__ . '/partials/header.php'; ?>

                <div class="mt-16">
                    <?= $content ?>
                </div>

                <?php include __DIR__ . '/partials/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html>
