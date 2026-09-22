<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
    <div class="scale-100 p-6 bg-white dark:bg-gray-800/50 dark:bg-gradient-to-bl from-gray-700/50 via-transparent dark:ring-1 dark:ring-inset dark:ring-white/5 rounded-lg shadow-2xl shadow-gray-500/20 dark:shadow-none flex motion-safe:hover:scale-[1.01] transition-all duration-250 focus:outline focus:outline-2 focus:outline-red-500">
        <div>
            <div class="h-16 w-16 bg-red-50 dark:bg-red-800/20 flex items-center justify-center rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-7 h-7 stroke-red-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </div>
            <h2 class="mt-6 text-xl font-semibold text-gray-900 dark:text-white">xModern Routing</h2>
            <p class="mt-4 text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                Elegant and intuitive routing system inspired by Laravel, with full support for RESTful resources.
            </p>
            <h1 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars((string) ($appname ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="mt-2 text-gray-500 dark:text-gray-400 text-sm"><?= htmlspecialchars((string) ($description ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-gray-500 dark:text-gray-400 text-sm">Author: <?= htmlspecialchars((string) ($author ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-gray-500 dark:text-gray-400 text-sm">Version: <?= htmlspecialchars((string) ($version ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-gray-500 dark:text-gray-400 text-sm">License: <?= htmlspecialchars((string) ($license ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div class="scale-100 p-6 bg-white dark:bg-gray-800/50 dark:bg-gradient-to-bl from-gray-700/50 via-transparent dark:ring-1 dark:ring-inset dark:ring-white/5 rounded-lg shadow-2xl shadow-gray-500/20 dark:shadow-none flex motion-safe:hover:scale-[1.01] transition-all duration-250 focus:outline focus:outline-2 focus:outline-red-500">
        <div>
            <div class="h-16 w-16 bg-red-50 dark:bg-red-800/20 flex items-center justify-center rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-7 h-7 stroke-red-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
            </div>
            <h2 class="mt-6 text-xl font-semibold text-gray-900 dark:text-white">Database Integration</h2>
            <p class="mt-4 text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                Seamless MySQL integration with Medoo, supporting modern SQL features and transactions.
            </p>
        </div>
    </div>

    <div class="scale-100 p-6 bg-white dark:bg-gray-800/50 dark:bg-gradient-to-bl from-gray-700/50 via-transparent dark:ring-1 dark:ring-inset dark:ring-white/5 rounded-lg shadow-2xl shadow-gray-500/20 dark:shadow-none flex motion-safe:hover:scale-[1.01] transition-all duration-250 focus:outline focus:outline-2 focus:outline-red-500">
        <div>
            <div class="h-16 w-16 bg-red-50 dark:bg-red-800/20 flex items-center justify-center rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="w-7 h-7 stroke-red-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33" />
                </svg>
            </div>
            <h2 class="mt-6 text-xl font-semibold text-gray-900 dark:text-white">Built-in Security</h2>
            <p class="mt-4 text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                Advanced security features including CORS support and built-in cipher tools for data encryption.
            </p>
        </div>
    </div>
</div>
