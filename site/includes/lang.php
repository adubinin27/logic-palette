<?php
/**
 * Logic Palette landing — texts (RU / EN).
 *
 * @author STM Webcode Systems (stm-project.ru)
 * @copyright 2026
 */

declare(strict_types=1);

return [
    'ru' => [
        'meta_title' => 'Logic Palette — бесплатная компактная палитра цветов для Logic Pro',
        'meta_desc' => 'Бесплатная лёгкая плавающая палитра для покраски треков и регионов в Logic Pro. Не закрывает рабочую область, три вида, масштабирование. macOS 13+.',
        'nav_features' => 'Преимущества',
        'nav_screens' => 'Скриншоты',
        'nav_install' => 'Установка',
        'nav_download' => 'Скачать',
        'lang_switch' => 'EN',
        'lang_switch_title' => 'Switch to English',

        'hero_badge' => 'Бесплатно · для Logic Pro · macOS',
        'hero_title' => 'Компактная палитра цветов для Logic Pro',
        'hero_lead' => 'Красьте треки и регионы в один клик. Logic Palette — маленькая плавающая панель с теми же 96 цветами, что и в Logic, но она не перекрывает треки и панель инструментов.',
        'hero_btn_pkg' => 'Скачать установщик',
        'hero_btn_dmg' => 'Образ диска (.dmg)',
        'hero_meta' => 'Бесплатно · Версия %s · %d КБ · macOS 13+ · Intel и Apple Silicon',
        'downloads_label' => ['скачивание', 'скачивания', 'скачиваний'],
        'hero_caption' => 'Слева — штатное окно Color, которое закрывает треки. Справа — Logic Palette.',

        'about_title' => 'Что это',
        'about_text' => [
            'Штатное окно Color в Logic Pro — широкая полоса во весь экран: оно перекрывает треки и панель инструментов, и его приходится постоянно открывать и закрывать.',
            'Logic Palette заменяет его компактной панелью, которую можно поставить у края экрана и держать открытой всё время. Выделите трек или регион, нажмите цвет — Logic покрасит его так же, как из родной палитры.',
        ],

        'space_title' => 'На MacBook каждый пиксель на счету',
        'space_lead' => 'На 13–14-дюймовом экране Logic и так тесно: инспектор, микшер, треки и панель инструментов делят одно окно. Вот как это выглядит на реальном MacBook.',
        'space_caption' => 'MacBook, Logic Pro 10.8: сверху — штатное окно Color, справа у края — Logic Palette.',
        'space_points' => [
            ['bad', 'Окно Color', 'Занимает около половины ширины экрана и встаёт прямо на панель инструментов: транспорт, дисплей и линейка скрыты, а верхний трек уходит под окно.'],
            ['good', 'Logic Palette', 'Узкая полоска у края — около 5% ширины экрана. Все 96 цветов под рукой, а треки, транспорт и инструменты остаются на виду.'],
            ['good', 'Без лишних кликов', 'Окно Color приходится открывать и закрывать ради каждого цвета. Палитру можно держать открытой весь сеанс или свернуть в маленькую кнопку.'],
        ],

        'features_title' => 'Почему Logic Palette',
        'features' => [
            ['Не закрывает рабочую область', 'Узкая панель вместо широкого окна поверх треков. Панель инструментов, линейка и треки Logic остаются на виду.'],
            ['Лёгкая', 'Меньше 1 МБ, нативный код macOS, без плагинов и фоновых сервисов. В простое не нагружает процессор.'],
            ['Гибкая', 'Три вида: вертикальный, горизонтальный и квадратный. Тяните за угол — палитра и плитки масштабируются. Сворачивается в маленькую кнопку.'],
            ['Те же 96 цветов', 'Точная палитра Logic Pro. Работает с треками и регионами, отмена — ⌘Z, как обычно.'],
            ['Безопасна для Logic', 'Не меняет файлы Logic и не встраивает в него код. Удаление — перетащить приложение в Корзину.'],
            ['Запускается вместе с Logic', 'Появляется при открытии Logic и прячется при выходе. Работает при любой раскладке клавиатуры, интерфейс на русском и английском.'],
        ],

        'screens_title' => 'Скриншоты',
        'screens' => [
            'vertical' => 'Вертикальный вид у края окна',
            'horizontal' => 'Горизонтальный вид — как в Logic, только компактнее',
            'square' => 'Квадратный вид',
            'resized' => 'Размер и форма меняются за угол',
        ],
        'lb_prev' => 'Предыдущий скриншот',
        'lb_next' => 'Следующий скриншот',
        'lb_close' => 'Закрыть',

        'how_title' => 'Как это работает',
        'how' => [
            ['Выделите', 'трек или регион в Logic Pro'],
            ['Нажмите', 'нужный цвет в Logic Palette'],
            ['Готово', 'Logic покрасит выделенное за долю секунды'],
        ],

        'install_title' => 'Установка',
        'install' => [
            'Скачайте установщик <b>.pkg</b> и откройте его — приложение установится в «Программы».',
            'Если macOS не открывает установщик: <b>Системные настройки → Конфиденциальность и безопасность → «Всё равно открыть»</b>. Приложение не нотариально заверено Apple, это нормально.',
            'Разрешите доступ: <b>Конфиденциальность и безопасность → Универсальный доступ</b> → включите «Logic Palette». Он нужен, чтобы нажимать цвета в окне Logic.',
            'Откройте Logic Pro — палитра появится сама. Настройки и язык — в меню по правому клику на палитре.',
        ],
        'install_terminal' => 'Если значок приложения перечёркнут или macOS пишет, что оно повреждено, выполните в Терминале, который вы открыли из папки «Программы» (Программы → Терминал):',
        'requirements_title' => 'Требования',
        'requirements' => ['macOS 13 Ventura и новее (включая Sequoia)', 'Logic Pro 10.8', 'Intel и Apple Silicon'],

        'faq_title' => 'Вопросы',
        'faq' => [
            ['Сколько стоит?', 'Нисколько — Logic Palette бесплатна. Без пробного периода, подписки, рекламы и регистрации.'],
            ['Меняет ли приложение Logic Pro?', 'Нет. Файлы Logic не изменяются, плагины не устанавливаются. Палитра лишь нажимает цвет в штатном окне Color за вас.'],
            ['Как удалить?', 'Правый клик по палитре → «Выход», затем перетащите «Logic Palette» из «Программ» в Корзину.'],
            ['Зачем доступ «Универсальный доступ»?', 'Без него macOS не разрешает программе нажимать кнопки в окнах других приложений — в нашем случае в палитре Logic.'],
        ],

        'developer' => 'Разработчик',
        'footer_note' => 'Logic Pro — товарный знак Apple Inc. Проект не связан с Apple.',
    ],

    'en' => [
        'meta_title' => 'Logic Palette — a free, compact color palette for Logic Pro',
        'meta_desc' => 'A free, lightweight floating palette for coloring tracks and regions in Logic Pro. Keeps your workspace clear, three layouts, resizable. macOS 13+.',
        'nav_features' => 'Features',
        'nav_screens' => 'Screenshots',
        'nav_install' => 'Install',
        'nav_download' => 'Download',
        'lang_switch' => 'RU',
        'lang_switch_title' => 'Переключить на русский',

        'hero_badge' => 'Free · for Logic Pro · macOS',
        'hero_title' => 'A compact color palette for Logic Pro',
        'hero_lead' => 'Color tracks and regions in one click. Logic Palette is a small floating panel with the same 96 colors as Logic — without covering your tracks and toolbar.',
        'hero_btn_pkg' => 'Download installer',
        'hero_btn_dmg' => 'Disk image (.dmg)',
        'hero_meta' => 'Free · Version %s · %d KB · macOS 13+ · Intel & Apple Silicon',
        'downloads_label' => ['download', 'downloads'],
        'hero_caption' => 'Left: Logic\'s native Color window covering the tracks. Right: Logic Palette.',

        'about_title' => 'What it is',
        'about_text' => [
            'Logic Pro\'s native Color window is a wide strip across the screen: it covers the tracks and the toolbar, and you keep opening and closing it.',
            'Logic Palette replaces it with a compact panel you can park at the edge of the screen and keep open all the time. Select a track or region, click a color — Logic paints it exactly like its own palette does.',
        ],

        'space_title' => 'On a MacBook, every pixel counts',
        'space_lead' => 'On a 13–14" screen Logic is already crowded: the inspector, mixer, tracks and toolbar all share one window. Here is what it looks like on a real MacBook.',
        'space_caption' => 'MacBook, Logic Pro 10.8: the native Color window on top, Logic Palette at the right edge.',
        'space_points' => [
            ['bad', 'Color window', 'Takes about half the screen width and lands right on the toolbar: transport, display and ruler are hidden, and the top track slides under it.'],
            ['good', 'Logic Palette', 'A narrow strip at the edge — about 5% of the screen width. All 96 colors at hand, while tracks, transport and tools stay visible.'],
            ['good', 'No extra clicks', 'The Color window has to be opened and closed for every color. The palette can stay open the whole session or collapse into a tiny button.'],
        ],

        'features_title' => 'Why Logic Palette',
        'features' => [
            ['Keeps your workspace clear', 'A slim panel instead of a wide window over your tracks. Logic\'s toolbar, ruler and tracks stay visible.'],
            ['Lightweight', 'Under 1 MB, native macOS code, no plug-ins or background services. Zero CPU load while idle.'],
            ['Flexible', 'Three layouts: vertical, horizontal and square. Drag the corner — the palette and its tiles scale. Collapses into a tiny button.'],
            ['The same 96 colors', 'The exact Logic Pro palette. Works with tracks and regions, undo with ⌘Z as usual.'],
            ['Safe for Logic', 'Doesn\'t modify Logic\'s files or inject any code. To remove it, just drag the app to the Trash.'],
            ['Starts with Logic', 'Appears when Logic launches and hides when it quits. Works with any keyboard layout; English and Russian interface.'],
        ],

        'screens_title' => 'Screenshots',
        'screens' => [
            'vertical' => 'Vertical layout at the window edge',
            'horizontal' => 'Horizontal layout — like Logic\'s, only compact',
            'square' => 'Square layout',
            'resized' => 'Resize and reshape by the corner',
        ],
        'lb_prev' => 'Previous screenshot',
        'lb_next' => 'Next screenshot',
        'lb_close' => 'Close',

        'how_title' => 'How it works',
        'how' => [
            ['Select', 'a track or region in Logic Pro'],
            ['Click', 'a color in Logic Palette'],
            ['Done', 'Logic paints the selection in a split second'],
        ],

        'install_title' => 'Installation',
        'install' => [
            'Download the <b>.pkg</b> installer and open it — the app is installed into Applications.',
            'If macOS refuses to open the installer: <b>System Settings → Privacy &amp; Security → “Open Anyway”</b>. The app isn\'t notarized by Apple, that\'s expected.',
            'Grant access: <b>Privacy &amp; Security → Accessibility</b> → turn on “Logic Palette”. It\'s needed to click colors in Logic\'s window.',
            'Open Logic Pro — the palette appears automatically. Settings and language are in the right-click menu on the palette.',
        ],
        'install_terminal' => 'If the app icon is crossed out or macOS says the app is damaged, run this in Terminal opened from the Applications folder (Applications → Terminal):',
        'requirements_title' => 'Requirements',
        'requirements' => ['macOS 13 Ventura or later (including Sequoia)', 'Logic Pro 10.8', 'Intel and Apple Silicon'],

        'faq_title' => 'FAQ',
        'faq' => [
            ['How much does it cost?', 'Nothing — Logic Palette is free. No trial, subscription, ads or sign-up.'],
            ['Does it modify Logic Pro?', 'No. Logic\'s files stay untouched and no plug-ins are installed. The palette simply clicks the color in Logic\'s native Color window for you.'],
            ['How do I uninstall it?', 'Right-click the palette → “Quit”, then drag “Logic Palette” from Applications to the Trash.'],
            ['Why does it need Accessibility access?', 'Without it macOS doesn\'t allow an app to press buttons in other apps\' windows — in this case, Logic\'s palette.'],
        ],

        'developer' => 'Developer',
        'footer_note' => 'Logic Pro is a trademark of Apple Inc. This project is not affiliated with Apple.',
    ],
];
