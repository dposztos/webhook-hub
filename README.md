# Webhook Hub

Saját üzemeltetésű webhook-gyűjtő és -automatizáló. A [webhook.site](https://github.com/fredsted/webhook.site)
MIT-licencű OSS változatából indult fork, de a v2 ág önálló alkalmazás: Laravel 12 + PostgreSQL + Vue 3.

Amit az eredeti nyílt verzió nem tudott, és itt megvan:

- **Csoport-hierarchia**: tetszőleges mélységű fa (pl. `Ügyfelek / ABC Kft. / Rendelések`), csoportonként
  akárhány URL. Minden URL külön endpoint, saját beállításokkal.
- **Minden üzenet megmarad**: nincs 500 kérés/URL korlát és nincs 7 napos lejárat. A megőrzés
  endpointonként korlátozható, ha valamit mégis takarítani kell.
- **Olvasatlan jelölés**: az új üzenet olvasatlan (kék jelölés a listán, számláló a fában a
  csoportokra összesítve is); a megnyitás olvasottnak jelöli, és van „mind olvasott" meg
  „vissza olvasatlanra" is.
- **Újraküldés bármivel**: minden üzenetnél ott a kész parancs curl, PowerShell, HTTPie, Python,
  JavaScript és nyers HTTP formában — az endpoint aktuális címére, a proxy-fejlécek nélkül.
- **Szabályok és akciók**: a beérkezett JSON mezőire (fejlécekre, query-paraméterekre) épülő,
  egymásba ágyazható ÉS/VAGY feltételek, és rájuk kötött akciók. Jelenleg: e-mail küldése HTML-sablonnal,
  a beérkezett adatokra hivatkozó változókkal.

## Az URL-ek felépítése

```
https://<host>/u/<csoport>/<alcsoport>/<endpoint>/<titok>[/<tetszőleges folytatás>]
                └──────── a fa útvonala ────────┘  └ 12 karakter, cserélhető
```

Példa: `https://webhook.posztos.com/u/ugyfelek/abc-kft/rendelesek/k7f3q9x2mnpq`

- Az útvonalból leolvasható, melyik ügyfél melyik folyamata küld — de a titok nélkül nem kitalálható.
- A titok cserélhető a felületen (**Beállítások → Új titok**); ilyenkor a régi URL azonnal 404-et ad.
- Bármi ráfűzhető a végére (a rendszer eltárolja), és a `/404`-hez hasonló záró szegmens felülírja a
  visszaadott státuszkódot.
- Átnevezés **nem** változtatja meg az URL-t: az útvonalat a slug adja, ami a létrehozáskor rögzül.

## Szabályok

Egy szabály vagy egy endpointhoz, vagy egy csoporthoz tartozik. A **csoportra tett szabály az alatta lévő
összes endpointra lefut** — így pl. „minden ABC Kft.-s hibás státusz esetén szólj” egy helyen megírható.
A szabályok prioritás szerint futnak; a `stop_processing` bekapcsolásával egy találat lezárja a sort.

Feltétel-források: `json` (a feldolgozott test – JSON, form-urlencoded és multipart is), `header`,
`query`, `meta` (method, ip, url, size, content_type…), `body` (nyers szöveg).

Mezőhivatkozás pont-jelöléssel: `order.items.0.sku`, illetve `order.items.*.sku` az összes elemre.

Operátorok: `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`, `ends_with`, `regex`,
`not_regex`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in`, `exists`, `not_exists`, `is_empty`, `is_not_empty`,
`is_true`, `is_false`. A számokat számként hasonlítja (a `"1 234"` alakot is), dátumot időbélyegként.

A szerkesztőben a **Kipróbálás a legutóbbi üzeneten** gomb megmutatja, illeszkedne-e a szabály, és
feltételenként kiírja, mi lett az eredmény.

## E-mail sablonok

A tárgy és a törzs [Twig](https://twig.symfony.com/) sablon, **homokozóban** futtatva: csak engedélyezett
tagek és szűrők mennek, függvény- és metódushívás nincs, a beérkezett adatok HTML-escape-elve kerülnek be.

```twig
<style>.total { font-size: 20px; font-weight: 700; color: #2563eb }</style>

<h2>Köszönjük a rendelést, {{ json.customer.name|default('Kedves Ügyfél') }}!</h2>
<p>Azonosító: <strong>{{ json.order.id }}</strong></p>
<p class="total">{{ json.order.total|huf }}</p>

{% for tetel in json.order.items %}
  <p>{{ tetel.sku }} – {{ tetel.db }} db</p>
{% endfor %}

{{ json|table }}
<p>Beérkezett: {{ meta.received_at_hu }}</p>
```

Elérhető változók: `json`, `body`, `headers`, `query`, `meta`, `endpoint`, `group`.
Saját szűrők: `huf` (24990 → „24 990 Ft”), `table` (tömb → HTML-táblázat), `json_pretty`, `hu_date`.
A `<style>` blokk küldés előtt inline stílusokká alakul, hogy a levelezőkliensek is helyesen mutassák.

A címzett is sablon lehet: `{{ json.customer.email }}, iroda@ceg.hu`. Az érvénytelen címeket a rendszer
kiszűri; ha nem marad egy sem, az akció hibás státusszal kerül a naplóba (a beérkezett üzenet megmarad).

## Fejlesztés

Nincs szükség helyi PHP-ra, minden konténerben fut:

```bash
docker compose -f docker/dev/compose.yml up -d      # Postgres + app (:8090) + queue worker
./php php artisan migrate                            # artisan a konténerben
./php php artisan db:seed --class=DemoSeeder         # példa csoport + endpoint + szabály
./php php artisan webhook:admin te@pelda.hu --password=…
npm install && npm run build                         # frontend
./php php artisan test                               # tesztek
```

## Telepítés

A `Dockerfile` háromlépcsős: Vite build → composer install → futtatókörnyezet (nginx + php-fpm +
supervisord, ami a queue workert és az ütemezőt is viszi). Az induláskor lefut a migráció, és ha az
`ADMIN_EMAIL`/`ADMIN_PASSWORD` be van állítva, létrejön (vagy frissül) az admin felhasználó.

A `docker/prod/stack.yml` a Portainer-stack: app + Postgres névvel ellátott kötetben.

Fontosabb környezeti változók:

| Változó | Jelentés |
| --- | --- |
| `APP_URL` | Ezzel a hosttal generálódnak a webhook-URL-ek |
| `SESSION_SECURE` | HTTPS mögött `true`, sima HTTP-s eléréshez `false` (különben nem megy a belépés) |
| `WEBHOOK_MAX_BODY_BYTES` | Ekkora méretig tároljuk a testet (fölötte csonkolva, jelzéssel) |
| `WEBHOOK_INGEST_RATE_LIMIT` | Beérkező kérés / perc / IP (0 = korlátlan) |
| `WEBHOOK_RETENTION_DAYS` | Globális alapértelmezett megőrzés; üresen: örökre |
| `WEBHOOK_ALLOWED_RECIPIENTS` | Ha kitöltöd, csak ezekre a mintákra illeszkedő címre megy levél |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Induláskor létrehozandó/frissítendő admin |

## Licenc

MIT – az eredeti webhook.site projekt licencét megtartva (lásd `LICENSE`).
