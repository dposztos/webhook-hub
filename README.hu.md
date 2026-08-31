# Webhook Hub

*[English README](README.md)*

Saját üzemeltetésű webhook-gyűjtő, ami megőrzi az üzeneteket, fába rendezi őket,
és tud is kezdeni valamit azzal, ami beesik.

A [webhook.site](https://github.com/fredsted/webhook.site) MIT-licencű OSS
változatából indult fork, de mára önálló alkalmazás: Laravel 12 + PostgreSQL +
Vue 3. A fork eredeti állapota az `upstream-master` ágon maradt meg.

Amit az eredeti nyílt verzió nem tudott, és itt megvan:

- **Csoport-hierarchia**: tetszőleges mélységű fa (pl. `Ügyfelek / ABC Kft. /
  Rendelések`), csoportonként akárhány URL. Minden URL külön endpoint, saját
  beállításokkal.
- **Minden üzenet megmarad**: nincs 500 kérés/URL korlát és nincs 7 napos
  lejárat. A megőrzés endpointonként korlátozható, ha valamit mégis takarítani
  kell.
- **Olvasatlan jelölés**: az új üzenet olvasatlan (kék jelölés a listán,
  számláló a fában a csoportokra összesítve is); a megnyitás olvasottnak
  jelöli, és van „mind olvasott" meg „vissza olvasatlanra" is.
- **Újraküldés bármivel**: minden üzenetnél ott a kész parancs curl,
  PowerShell, HTTPie, Python, JavaScript és nyers HTTP formában — az endpoint
  aktuális címére, a proxy-fejlécek nélkül.
- **Szabályok és akciók**: a beérkezett JSON mezőire (fejlécekre,
  query-paraméterekre) épülő, egymásba ágyazható ÉS/VAGY feltételek, és rájuk
  kötött akciók. Jelenleg: e-mail küldése HTML-sablonnal, a beérkezett adatokra
  hivatkozó változókkal, illetve Python szkript futtatása, ami az üzenetet a
  standard bemenetén kapja meg.

## Kipróbálás

```bash
git clone https://github.com/dposztos/webhook-hub.git
cd webhook-hub
cp .env.example .env
# a kiírt kulcs az APP_KEY-be, plusz DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD
docker compose run --rm app php artisan key:generate --show
docker compose up -d
```

A felület a <http://localhost:8080> címen jön fel. A belépés a `.env`-ben
megadott `ADMIN_EMAIL` / `ADMIN_PASSWORD` párossal megy — nyilvános regisztráció
nincs.

Kész image-ek: `ghcr.io/dposztos/webhook-hub:latest` és
`dposztos/webhook-hub:latest`.

## Az URL-ek felépítése

```
https://<host>/u/<csoport>/<alcsoport>/<endpoint>/<titok>[/<tetszőleges folytatás>]
                └──────── a fa útvonala ────────┘  └ 12 karakter, cserélhető
```

Példa: `https://webhook.example.com/u/ugyfelek/abc-kft/rendelesek/k7f3q9x2mnpq`

- Az útvonalból leolvasható, melyik ügyfél melyik folyamata küld — de a titok
  nélkül nem kitalálható.
- A titok cserélhető a felületen (**Beállítások → Új titok**); ilyenkor a régi
  URL azonnal 404-et ad.
- Bármi ráfűzhető a végére (a rendszer eltárolja), és a `/404`-hez hasonló záró
  szegmens felülírja a visszaadott státuszkódot.
- Átnevezés **nem** változtatja meg az URL-t: az útvonalat a slug adja, ami a
  létrehozáskor rögzül.

## Szabályok

Egy szabály vagy egy endpointhoz, vagy egy csoporthoz tartozik. A **csoportra
tett szabály az alatta lévő összes endpointra lefut** — így pl. „minden ABC
Kft.-s hibás státusz esetén szólj" egy helyen megírható. A szabályok prioritás
szerint futnak; a `stop_processing` bekapcsolásával egy találat lezárja a sort.

Feltétel-források: `json` (a feldolgozott test – JSON, form-urlencoded és
multipart is), `header`, `query`, `meta` (method, ip, url, size, content_type…),
`body` (nyers szöveg).

Mezőhivatkozás pont-jelöléssel: `order.items.0.sku`, illetve
`order.items.*.sku` az összes elemre.

Operátorok: `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`,
`ends_with`, `regex`, `not_regex`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in`,
`exists`, `not_exists`, `is_empty`, `is_not_empty`, `is_true`, `is_false`. A
számokat számként hasonlítja (a `"1 234"` alakot is), dátumot időbélyegként.

A szerkesztőben a **Kipróbálás a legutóbbi üzeneten** gomb megmutatja,
illeszkedne-e a szabály, és feltételenként kiírja, mi lett az eredmény.

## E-mail sablonok

A tárgy és a törzs [Twig](https://twig.symfony.com/) sablon, **homokozóban**
futtatva: csak engedélyezett tagek és szűrők mennek, függvény- és metódushívás
nincs, a beérkezett adatok HTML-escape-elve kerülnek be.

```twig
<style>.total { font-size: 20px; font-weight: 700; color: #2563eb }</style>

<h2>Köszönjük a rendelést, {{ json.customer.name|default('Kedves Ügyfél') }}!</h2>
<p>Azonosító: <strong>{{ json.order.id }}</strong></p>
<p class="total">{{ json.order.total|money(' Ft', 0, ',', ' ') }}</p>

{% for tetel in json.order.items %}
  <p>{{ tetel.sku }} – {{ tetel.db }} db</p>
{% endfor %}

{{ json|table }}
<p>Beérkezett: {{ meta.received_at_local }}</p>
```

Elérhető változók: `json`, `body`, `headers`, `query`, `meta`, `endpoint`,
`group`. Saját szűrők: `money` (a formátum a `config/webhookhub.php` `money`
blokkjából jön, illetve szűrő-paraméterből), `table` (tömb → HTML-táblázat),
`json_pretty`, `local_date`. A `<style>` blokk küldés előtt inline stílusokká
alakul, hogy a levelezőkliensek is helyesen mutassák.

> A korábbi magyar szűrőnevek (`huf`, `hu_date`) és a `meta.received_at_hu`
> változó továbbra is működnek, hogy a régebben mentett sablonok ne törjenek el
> — de az újakat érdemes a locale-semleges nevekkel írni.

A címzett is sablon lehet: `{{ json.customer.email }}, iroda@ceg.hu`. Az
érvénytelen címeket a rendszer kiszűri; ha nem marad egy sem, az akció hibás
státusszal kerül a naplóba (a beérkezett üzenet megmarad).

## Szkript-akciók

Egy szabály Python szkriptet is futtathat a beérkező webhookra. Az üzenet
JSON-ként érkezik a szkript standard bemenetére, a kilépési kód dönti el, hogy
az akció sikeres volt-e, a kimenet pedig a futás mellé kerül.

```python
import json, sys

payload = json.load(sys.stdin)
print(json.dumps({"queued": payload["json"]["order"]["id"]}))
```

A szkriptek egy mappából jönnek, amit a konténerbe csatolsz — csak az ott lévő
fájlok futtathatók; külön engedéllyel a szabály saját, beírt kódot is tarthat.
Az egész funkció ki van kapcsolva, amíg be nem állítod a
`WEBHOOK_SCRIPTS_ENABLED=true`-t: ez kódfuttatás a szerveren, az admin felületről
vezérelve.

Részletek, korlátok és a biztonsági mérlegelés: [docs/scripts.md](docs/scripts.md).
IBM i (AS/400) lekérdezése ODBC-n keresztül: [docs/as400.md](docs/as400.md).

## Beállítások

| Változó | Jelentés |
| --- | --- |
| `APP_URL` | Ezzel a hosttal generálódnak a webhook-URL-ek |
| `APP_LOCALE` | A felület és a levelek nyelve (`en`, `hu`) |
| `SESSION_SECURE` | HTTPS mögött `true`, sima HTTP-s eléréshez `false` (különben nem megy a belépés) |
| `WEBHOOK_MAX_BODY_BYTES` | Ekkora méretig tároljuk a testet (fölötte csonkolva, jelzéssel) |
| `WEBHOOK_INGEST_RATE_LIMIT` | Beérkező kérés / perc / IP (0 = korlátlan) |
| `WEBHOOK_RETENTION_DAYS` | Globális alapértelmezett megőrzés; üresen: örökre |
| `WEBHOOK_ALLOWED_RECIPIENTS` | Ha kitöltöd, csak ezekre a mintákra illeszkedő címre megy levél |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Induláskor létrehozandó/frissítendő admin |

A teljes lista a [`.env.example`](.env.example) fájlban.

## Telepítés

A `Dockerfile` háromlépcsős: Vite build → composer install → futtatókörnyezet
(nginx + php-fpm + supervisord, ami a queue workert és az ütemezőt is viszi). Az
induláskor lefut a migráció, és ha az `ADMIN_EMAIL`/`ADMIN_PASSWORD` be van
állítva, létrejön (vagy frissül) az admin felhasználó.

A `docker/prod/stack.yml` ugyanez Portainer-stackként.

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

## Közreműködés

Hibajelzés, ötlet és pull request jöhet — a részletek a
[CONTRIBUTING.md](CONTRIBUTING.md)-ben. **Fordítás különösen**: egy új nyelvhez
a `lang/en.json` és a `lang/en/` másolatát kell lefordítani, más dolgod nincs.

Biztonsági hibát ne nyilvános issue-ban jelents, hanem a GitHub privát
bejelentőjén keresztül; a hatókört a [SECURITY.md](SECURITY.md) írja le.

## Licenc

MIT – az eredeti webhook.site projekt licencét megtartva (lásd
[LICENSE](LICENSE)).
