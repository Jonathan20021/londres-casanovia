<?php
/**
 * Componentes de interfaz reutilizables: iconos SVG, badges de estado,
 * alertas, paginación, avatares y tarjetas de producto.
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/* ================================================================== *
 *  ICONOS (Heroicons outline + marcas). icon('home','w-5 h-5')
 * ================================================================== */
function icon(string $name, string $class = 'w-5 h-5'): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = [
            'home'        => ['s', '<path d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.5a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 .75-.75V15a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75v5.25c0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75V9.75M8.25 21h8.25"/>'],
            'box'         => ['s', '<path d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>'],
            'tag'         => ['s', '<path d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path d="M6 6h.008v.008H6V6Z"/>'],
            'squares'     => ['s', '<path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>'],
            'calendar'    => ['s', '<path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>'],
            'users'       => ['s', '<path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>'],
            'user'        => ['s', '<path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>'],
            'document'    => ['s', '<path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>'],
            'banknotes'   => ['s', '<path d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 1-.75.75v.75m0-15v.75c0 .414.336.75.75.75h.75M3.75 18v-.75c0-.414-.336-.75-.75-.75H2.25m1.5 1.5h-.375a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125h.375m16.5 1.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-15 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/>'],
            'bag'         => ['s', '<path d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>'],
            'chart'       => ['s', '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>'],
            'cog'         => ['s', '<path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'],
            'logout'      => ['s', '<path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>'],
            'bell'        => ['s', '<path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>'],
            'search'      => ['s', '<path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>'],
            'menu'        => ['s', '<path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>'],
            'sun'         => ['s', '<path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>'],
            'moon'        => ['s', '<path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>'],
            'plus'        => ['s', '<path d="M12 4.5v15m7.5-7.5h-15"/>'],
            'pencil'      => ['s', '<path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>'],
            'trash'       => ['s', '<path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>'],
            'eye'         => ['s', '<path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'],
            'check'       => ['s', '<path d="m4.5 12.75 6 6 9-13.5"/>'],
            'x'           => ['s', '<path d="M6 18 18 6M6 6l12 12"/>'],
            'warning'     => ['s', '<path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>'],
            'truck'       => ['s', '<path d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-6.375m0 0V5.625m0 13.125V5.625m0 0H3.375c-.621 0-1.125.504-1.125 1.125v9.75c0 .621.504 1.125 1.125 1.125H10.125m4.125-12h2.625c.621 0 1.214.276 1.59.749l2.034 2.542"/>'],
            'return'      => ['s', '<path d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>'],
            'clock'       => ['s', '<path d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>'],
            'lock'        => ['s', '<path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>'],
            'grip'        => ['f', '<circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/>'],
            'star'        => ['s', '<path d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>'],
            'inbox'       => ['s', '<path d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/>'],
            'photo'       => ['s', '<path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>'],
            'upload'      => ['s', '<path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>'],
            'filter'      => ['s', '<path d="M3.792 2.938A49.069 49.069 0 0 1 12 2.25c2.797 0 5.54.236 8.209.688a1.857 1.857 0 0 1 1.541 1.836v1.044a3 3 0 0 1-.879 2.121l-6.182 6.182a1.5 1.5 0 0 0-.439 1.061v2.927a3 3 0 0 1-1.658 2.684l-1.757.878A.75.75 0 0 1 9.75 21v-5.818a1.5 1.5 0 0 0-.44-1.06L3.13 7.938a3 3 0 0 1-.879-2.121V4.774c0-.897.64-1.683 1.542-1.836Z"/>'],
            'dots'        => ['s', '<path d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"/>'],
            'chevron-down'  => ['s', '<path d="m19.5 8.25-7.5 7.5-7.5-7.5"/>'],
            'chevron-right' => ['s', '<path d="m8.25 4.5 7.5 7.5-7.5 7.5"/>'],
            'chevron-left'  => ['s', '<path d="M15.75 19.5 8.25 12l7.5-7.5"/>'],
            'phone'       => ['s', '<path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>'],
            'pin'         => ['s', '<path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>'],
            'mail'        => ['s', '<path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>'],
            'sparkles'    => ['s', '<path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>'],
            'heart'       => ['s', '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>'],
            'printer'     => ['s', '<path d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>'],
            'download'    => ['s', '<path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>'],
            'whatsapp'    => ['f', '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>'],
            'instagram'   => ['f', '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>'],
            'square'      => ['s', '<path d="M3.75 3.75h16.5v16.5H3.75z"/>'],
            // Tijeras: piezas marcadas para modificar (ruedo, cintura…)
            'scissors'    => ['s', '<path d="M7.848 8.25l1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c0 .380-.098.736-.27 1.047M9.384 9.137l4.316 2.492M9.384 14.863l-1.536.887M9.384 14.863a2.165 2.165 0 0 0 1.083-1.838c0-.380-.098-.737-.27-1.048m-.813 2.886L14.7 11.63m-6.852 4.12a3 3 0 1 0-5.196 3 3 3 0 0 0 5.196-3Zm2.652-3.737 8.048-4.646a3 3 0 0 1 2.598 0M10.2 12.37l8.048 4.646a3 3 0 0 0 2.598 0"/>'],
        ];
    }
    [$mode, $inner] = $icons[$name] ?? $icons['square'];
    if ($mode === 'f') {
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $inner . '</svg>';
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/* ================================================================== *
 *  BADGES DE ESTADO
 *  status_badge('available', 'commercial')
 * ================================================================== */
function status_badge(?string $status, string $group = 'commercial'): string
{
    $maps = [
        'commercial' => [
            'available'   => ['Disponible',        'emerald'],
            'reserved'    => ['Reservado',         'sky'],
            'rented'      => ['Alquilado',         'amber'],
            'sold'        => ['Vendido',           'violet'],
            'unavailable' => ['No disponible',     'gray'],
            'maintenance' => ['En reparación',     'yellow'],
        ],
        'condition' => [
            'new'            => ['Nuevo',           'emerald'],
            'excellent'      => ['Excelente',       'sky'],
            'good'           => ['Bueno',           'indigo'],
            'repair'         => ['En reparación',   'yellow'],
            'out_of_service' => ['Fuera de servicio','rose'],
        ],
        'rental' => [
            'pending'        => ['Solicitud pendiente', 'gray'],
            'reserved'       => ['Reservado',           'sky'],
            'confirmed'      => ['Confirmado',          'indigo'],
            'delivered'      => ['Entregado',           'amber'],
            'pending_return' => ['Pendiente devolución','rose'],
            'returned'       => ['Devuelto',            'emerald'],
            'cancelled'      => ['Cancelado',           'gray'],
            'overdue'        => ['Vencido',             'red'],
        ],
        'payment' => [
            'pending' => ['Pendiente', 'gray'],
            'partial' => ['Parcial',   'amber'],
            'paid'    => ['Pagado',    'emerald'],
            'overdue' => ['Vencido',   'red'],
        ],
        'invoice' => [
            'pending' => ['Pendiente',           'gray'],
            'partial' => ['Parcialmente pagada', 'amber'],
            'paid'    => ['Pagada',              'emerald'],
            'void'    => ['Anulada',             'rose'],
        ],
        'request' => [
            'pending'   => ['Pendiente',  'amber'],
            'reviewed'  => ['Revisada',   'sky'],
            'converted' => ['Convertida', 'emerald'],
            'rejected'  => ['Rechazada',  'rose'],
            'cancelled' => ['Cancelada',  'gray'],
        ],
        'sale' => [
            'pending'   => ['Pendiente',  'amber'],
            'completed' => ['Completada', 'emerald'],
            'cancelled' => ['Cancelada',  'gray'],
        ],
        'user' => [
            'active'   => ['Activo',   'emerald'],
            'inactive' => ['Inactivo', 'gray'],
        ],
        'generic' => [
            'active'   => ['Activo',   'emerald'],
            'inactive' => ['Inactivo', 'gray'],
        ],
    ];

    $colors = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'sky'     => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'violet'  => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'indigo'  => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'yellow'  => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'red'     => 'bg-red-50 text-red-700 ring-red-600/20',
        'gray'    => 'bg-gray-100 text-gray-600 ring-gray-500/20',
    ];

    $map = $maps[$group] ?? $maps['generic'];
    [$label, $color] = $map[$status] ?? [ucfirst((string) $status), 'gray'];
    $cls = $colors[$color] ?? $colors['gray'];

    return '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ' . $cls . '">'
         . '<span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>' . e($label) . '</span>';
}

/* ================================================================== *
 *  AVATAR con iniciales
 * ================================================================== */
function avatar(?string $name, string $size = 'h-9 w-9 text-sm'): string
{
    $initials = user_initials($name);
    return '<span class="inline-flex ' . e($size) . ' items-center justify-center rounded-full bg-gradient-to-br from-brand-red to-rose-700 font-semibold text-white shadow-sm">'
         . e($initials) . '</span>';
}

/* ================================================================== *
 *  ALERTAS / FLASH
 * ================================================================== */
function render_flash(): string
{
    $styles = [
        'success' => ['emerald', 'check'],
        'error'   => ['rose', 'warning'],
        'warning' => ['amber', 'warning'],
        'info'    => ['sky', 'bell'],
    ];
    $out = '';
    foreach (flash_all() as $type => $message) {
        [$color, $ic] = $styles[$type] ?? $styles['info'];
        $out .= '<div data-flash class="mb-4 flex items-start gap-3 rounded-2xl border border-' . $color . '-200 bg-' . $color . '-50 px-4 py-3 text-sm text-' . $color . '-800 shadow-soft">'
              . '<span class="mt-0.5 text-' . $color . '-500">' . icon($ic, 'w-5 h-5') . '</span>'
              . '<div class="flex-1">' . e($message) . '</div>'
              . '<button type="button" onclick="this.closest(\'[data-flash]\').remove()" class="text-' . $color . '-400 hover:text-' . $color . '-600">' . icon('x', 'w-4 h-4') . '</button>'
              . '</div>';
    }
    return $out;
}

/* ================================================================== *
 *  PAGINACIÓN
 * ================================================================== */
function render_pagination(int $page, int $pages, string $pageParam = 'page'): string
{
    if ($pages <= 1) return '';
    $btn = function (int $p, string $inner, bool $disabled, string $title) use ($pageParam) {
        $base = 'flex h-9 w-9 items-center justify-center rounded-full border text-sm transition';
        if ($disabled) {
            return '<span class="' . $base . ' cursor-not-allowed border-gray-100 text-gray-300">' . $inner . '</span>';
        }
        return '<a href="' . e(query_url([$pageParam => $p])) . '" title="' . e($title) . '" class="' . $base . ' border-gray-200 bg-white text-gray-500 hover:border-brand-red hover:text-brand-red">' . $inner . '</a>';
    };

    $nav  = '<nav class="flex items-center gap-1.5">';
    $nav .= $btn(1, '&laquo;', $page <= 1, 'Primera');
    $nav .= $btn($page - 1, icon('chevron-left', 'w-4 h-4'), $page <= 1, 'Anterior');
    $nav .= $btn($page + 1, icon('chevron-right', 'w-4 h-4'), $page >= $pages, 'Siguiente');
    $nav .= $btn($pages, '&raquo;', $page >= $pages, 'Última');
    $nav .= '</nav>';

    return '<div class="flex w-full items-center justify-between gap-4">'
         . '<p class="text-sm text-gray-500">Página <span class="font-semibold text-gray-900">' . $page . '</span> de <span class="font-semibold text-gray-900">' . $pages . '</span></p>'
         . $nav . '</div>';
}

/* ================================================================== *
 *  MENÚ DE ACCIONES POR FILA (3 puntos) — Shoplytic style
 *  row_menu('menu-id', [
 *    ['label'=>'Ver','url'=>...,'icon'=>'eye'],
 *    ['label'=>'Editar','url'=>...,'icon'=>'pencil'],
 *    ['label'=>'Eliminar','url'=>postUrl,'icon'=>'trash','danger'=>true,'post'=>true,'confirm'=>'¿…?','fields'=>['id'=>5]],
 *  ])
 *  El menú se posiciona de forma fija vía JS (no lo recorta el overflow de la tabla).
 * ================================================================== */
function row_menu(string $id, array $items): string
{
    $btn = '<button type="button" data-menu-button="' . e($id) . '" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" title="Acciones">' . icon('dots', 'w-5 h-5') . '</button>';

    $menu = '<div id="' . e($id) . '" data-row-menu class="hidden w-48 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-card" style="position:fixed;z-index:60">';
    foreach ($items as $it) {
        $label = e($it['label'] ?? '');
        $ic    = icon($it['icon'] ?? 'eye', 'w-4 h-4');
        $danger = !empty($it['danger']);
        $cls   = $danger ? 'text-rose-600 hover:bg-rose-50' : 'text-gray-600 hover:bg-gray-50';
        if (!empty($it['post'])) {
            $confirm = !empty($it['confirm']) ? ' data-confirm="' . e($it['confirm']) . '"' : '';
            $menu .= '<form method="post" action="' . e($it['url']) . '"' . $confirm . '>' . csrf_field();
            foreach (($it['fields'] ?? []) as $k => $v) {
                $menu .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
            }
            $menu .= '<button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-sm ' . $cls . '">' . $ic . ' ' . $label . '</button></form>';
        } else {
            $menu .= '<a href="' . e($it['url'] ?? '#') . '" class="flex items-center gap-2.5 px-4 py-2 text-sm ' . $cls . '">' . $ic . ' ' . $label . '</a>';
        }
    }
    $menu .= '</div>';

    return '<div class="relative inline-block text-left">' . $btn . $menu . '</div>';
}

/* ================================================================== *
 *  EMPTY STATE
 * ================================================================== */
function empty_state(string $title, string $message = '', string $iconName = 'inbox', string $actionHtml = ''): string
{
    return '<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 px-6 py-16 text-center">'
         . '<span class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-gray-400 shadow-soft">' . icon($iconName, 'w-8 h-8') . '</span>'
         . '<h3 class="text-base font-semibold text-gray-900">' . e($title) . '</h3>'
         . ($message ? '<p class="mt-1 max-w-sm text-sm text-gray-500">' . e($message) . '</p>' : '')
         . ($actionHtml ? '<div class="mt-5">' . $actionHtml . '</div>' : '')
         . '</div>';
}

/* ================================================================== *
 *  TARJETA DE PRODUCTO (grid público)
 * ================================================================== */
function product_card(array $p): string
{
    $img    = upload_url($p['main_image'] ?? null);
    $name   = e($p['name'] ?? '');
    $cat    = e($p['category_name'] ?? 'General');
    $slug   = e($p['slug'] ?? '');
    $status = $p['commercial_status'] ?? 'available';
    $detail = pub_url('producto.php?slug=' . $slug);

    // Solo se anuncia precio de alquiler si la pieza se alquila y tiene precio.
    // Los complementos (velo, corona…) pueden ir a 0: ahí se pide consultar.
    $type   = $p['type'] ?? 'rental';
    $rental = (in_array($type, ['rental', 'both'], true) && (float) ($p['rental_price'] ?? 0) > 0)
        ? money($p['rental_price'])
        : null;
    $sale   = (in_array($type, ['sale', 'both'], true) && (float) ($p['sale_price'] ?? 0) > 0)
        ? money($p['sale_price'])
        : null;

    $disabled = in_array($status, ['rented', 'sold', 'unavailable'], true);

    $html  = '<article data-reveal class="group relative flex flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-soft transition duration-300 hover:-translate-y-1 hover:shadow-card">';
    $html .= '<a href="' . $detail . '" class="relative block aspect-[3/4] overflow-hidden bg-gray-100">';
    $html .= '<img src="' . e($img) . '" alt="' . $name . '" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">';
    $html .= '<span class="absolute left-3 top-3">' . status_badge($status, 'commercial') . '</span>';
    if (!empty($p['featured'])) {
        $html .= '<span class="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-brand-dark/80 px-2.5 py-1 text-xs font-medium text-brand-gold backdrop-blur">' . icon('sparkles', 'w-3.5 h-3.5') . 'Destacado</span>';
    }
    $html .= '</a>';
    $html .= '<div class="flex flex-1 flex-col p-4">';
    $html .= '<p class="text-xs font-medium uppercase tracking-wide text-brand-red">' . $cat . '</p>';
    $html .= '<h3 class="mt-1 font-serif text-lg leading-tight text-gray-900"><a href="' . $detail . '" class="hover:text-brand-red">' . $name . '</a></h3>';
    $html .= '<div class="mt-3 flex items-end justify-between">';
    $html .= '<div>';
    if ($rental) {
        $html .= '<p class="text-xs text-gray-400">Alquiler desde</p><p class="text-lg font-semibold text-gray-900">' . e($rental) . '</p>';
    } elseif ($sale) {
        $html .= '<p class="text-xs text-gray-400">Precio de venta</p><p class="text-lg font-semibold text-gray-900">' . e($sale) . '</p>';
    } else {
        $html .= '<p class="text-xs text-gray-400">Precio</p><p class="text-base font-semibold text-gray-500">Consultar</p>';
    }
    if ($rental && $sale) $html .= '<p class="mt-0.5 text-xs text-gray-400">Venta ' . e($sale) . '</p>';
    $html .= '</div></div>';
    $html .= '<div class="mt-4 flex gap-2">';
    $html .= '<a href="' . $detail . '" class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-center text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50">Ver detalles</a>';
    if (!$disabled) {
        $html .= '<a href="' . pub_url('solicitud-alquiler.php?product=' . (int) ($p['id'] ?? 0)) . '" class="flex-1 rounded-xl bg-brand-red px-3 py-2 text-center text-sm font-medium text-white transition hover:bg-red-700">Solicitar</a>';
    } else {
        $html .= '<span class="flex-1 rounded-xl bg-gray-100 px-3 py-2 text-center text-sm font-medium text-gray-400">No disponible</span>';
    }
    $html .= '</div></div></article>';
    return $html;
}

/* ================================================================== *
 *  LOCKUP DE MARCA — logo oficial de LONDRES Casa de Novias
 *  brand_lockup('light'|'dark', 'sm'|'md'|'lg')
 *
 *  El logo es un SVG vectorial sin fondo (public/assets/img/logo-londres.svg)
 *  extraído del original. La tipografía va con `currentColor`, así que el
 *  mismo archivo sirve sobre fondo claro (tinta oscura) y sobre fondo oscuro
 *  (tinta blanca); la cabina conserva siempre su rojo original.
 * ================================================================== */
function brand_lockup(string $tone = 'dark', string $size = 'md'): string
{
    $alt = e(setting('business_name', APP_NAME));

    // Un logo subido desde Configuración tiene prioridad sobre el oficial
    $custom = setting('logo');
    if (!empty($custom)) {
        $h = ['sm' => 'h-9', 'md' => 'h-11', 'lg' => 'h-16'][$size] ?? 'h-11';
        return '<img src="' . e(upload_url($custom)) . '" alt="' . $alt . '" class="' . $h . ' w-auto object-contain">';
    }

    // Altura por tamaño (el ancho se ajusta solo: el SVG mantiene su proporción)
    $h = ['sm' => 'h-9', 'md' => 'h-12', 'lg' => 'h-20'][$size] ?? 'h-12';
    $ink = $tone === 'light' ? 'text-white' : 'text-brand-dark';

    $logo = LCN_ROOT . '/public/assets/img/logo-londres.svg';
    if (is_file($logo)) {
        // Se incrusta el SVG para que `currentColor` herede el tono del contexto.
        $svg = (string) file_get_contents($logo);
        $svg = preg_replace(
            '/<svg /',
            '<svg class="' . $h . ' w-auto" ',
            $svg,
            1
        );
        return '<span class="' . $ink . ' inline-flex items-center" role="img" aria-label="' . $alt . '">' . $svg . '</span>';
    }

    // Respaldo tipográfico por si faltara el archivo
    $script = $tone === 'light' ? 'text-brand-gold' : 'text-brand-red';
    $sizes  = [
        'sm' => ['word' => 'text-xl',  'sub' => 'text-xs'],
        'md' => ['word' => 'text-2xl', 'sub' => 'text-sm'],
        'lg' => ['word' => 'text-4xl', 'sub' => 'text-lg'],
    ][$size] ?? ['word' => 'text-2xl', 'sub' => 'text-sm'];

    return '<span class="inline-flex flex-col leading-none">'
         . '<span class="font-serif font-bold tracking-[0.18em] ' . $sizes['word'] . ' ' . $ink . '">LONDRES</span>'
         . '<span class="font-script ' . $sizes['sub'] . ' ' . $script . ' -mt-0.5 ml-0.5">Casa de Novias</span>'
         . '</span>';
}

/* ================================================================== *
 *  TARJETA DE MÉTRICA (dashboard)
 * ================================================================== */
function metric_card(string $label, $value, string $iconName, string $tone = 'red', string $sub = ''): string
{
    $tones = [
        'red'     => 'bg-red-50 text-brand-red',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'sky'     => 'bg-sky-50 text-sky-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'violet'  => 'bg-violet-50 text-violet-600',
        'gold'    => 'bg-amber-50 text-brand-gold',
        'gray'    => 'bg-gray-100 text-gray-600',
    ];
    $t = $tones[$tone] ?? $tones['red'];
    return '<div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-card">'
         . '<div class="flex items-center justify-between">'
         . '<p class="text-sm font-medium text-gray-500">' . e($label) . '</p>'
         . '<span class="flex h-9 w-9 items-center justify-center rounded-lg ' . $t . '">' . icon($iconName, 'w-5 h-5') . '</span>'
         . '</div>'
         . '<p class="mt-3 text-3xl font-bold tracking-tight text-gray-900">' . e((string) $value) . '</p>'
         . ($sub ? '<p class="mt-1 text-xs text-gray-400">' . e($sub) . '</p>' : '')
         . '</div>';
}
