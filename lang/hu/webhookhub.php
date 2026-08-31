<?php

return [
    'console' => [
        'admin_description' => 'Admin felhasználó létrehozása vagy jelszavának cseréje',
        'admin_email' => 'E-mail cím',
        'admin_password' => 'Jelszó',
        'admin_required' => 'E-mail és jelszó kötelező.',
        'admin_password_short' => 'A jelszó legyen legalább 10 karakter.',
        'admin_created' => 'Admin létrehozva: :email',
        'admin_updated' => 'Jelszó frissítve: :email',

        'prune_dry_run' => 'Csak számol, nem töröl',
        'prune_description' => 'Endpointonként beállított megőrzési szabályok érvényesítése (alapból: örökre megőrzünk mindent)',
        'prune_marked' => 'Törlésre jelölve: :count üzenet (próbafuttatás)',
        'prune_deleted' => 'Törölve: :count üzenet',

        'recount_description' => 'Az endpointok üzenetszámlálóinak újraszámolása a tárolt üzenetekből',
        'recount_fixed' => 'Javítva: :count endpoint számlálója',
        'recount_ok' => 'Minden számláló pontos volt.',
    ],

    'validation' => [
        'group_cycle' => 'A csoport nem kerülhet önmaga (vagy saját leszármazottja) alá.',
        'rule_scope' => 'Egy szabály vagy csoporthoz, vagy endpointhoz tartozik – nem mindkettőhöz.',
    ],

    'conditions' => [
        'root' => 'feltételek',
        'too_deep' => 'Túl mélyen egymásba ágyazott feltételek.',
        'bad_operator_join' => ':path: az összekötés csak ÉS vagy VAGY lehet.',
        'bad_child' => ':path: érvénytelen elem a(z) :index. helyen.',
        'unknown_source' => ':path: ismeretlen mezőforrás (:source).',
        'unknown_operator' => ':path: ismeretlen operátor (:operator).',
        'path_required' => ':path: a mező neve kötelező.',
        'bad_regex' => ':path: érvénytelen reguláris kifejezés.',
    ],

    'actions' => [
        'unknown_type' => 'Ismeretlen akció-típus: :type',
        'limit_reached' => 'Akció-korlát elérve (:limit akció/üzenet)',
        'rule_failed' => 'Szabály kiértékelése elszállt',
    ],

    'email' => [
        'no_recipient' => 'Nincs érvényes címzett (a "to" sablon üresre vagy hibás címre értékelődött).',
        'default_subject' => 'Webhook értesítés',
        'recipient_blocked' => 'A címzett nincs engedélyezve (WEBHOOK_ALLOWED_RECIPIENTS): :addresses',
        'dry_run' => 'Próbafuttatás – nem ment ki levél',
        'sent' => 'Elküldve: :addresses',
    ],

    'script' => [
        'disabled' => 'A szkript-akciók ki vannak kapcsolva (WEBHOOK_SCRIPTS_ENABLED).',
        'inline_disabled' => 'A beírt kód futtatása ki van kapcsolva (WEBHOOK_SCRIPTS_ALLOW_INLINE); válassz fájlt a szkript-mappából.',
        'no_directory' => 'A szkript-mappa nem létezik: :dir',
        'bad_path' => 'Érvénytelen szkript-útvonal: :path',
        'not_found' => 'A szkript nincs meg a szkript-mappában: :path',
        'not_python' => 'Csak .py fájl futtatható: :path',
        'no_code' => 'A beírt szkript üres.',
        'temp_failed' => 'Nem sikerült ideiglenes szkriptfájlt írni ide: :dir',
        'dry_run' => 'Próbafuttatás — a szkript nem indult el',
        'ok' => 'Lefutott: :name',
        'exit_code' => 'A szkript :code hibakóddal állt le — :error',
        'timeout' => 'A szkript :seconds másodperc után le lett állítva.',
        'truncated' => 'a kimenet levágva',
    ],

    'template' => [
        'error' => 'Sablonhiba (:line. sor): :message',
        'empty_table' => 'üres',
    ],
];
