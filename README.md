# 🚀 WooCommerce Order Processor

Lehká PHP aplikace pro agregaci a sledování objednávek z více WooCommerce shopů na jednom místě.

---

## ⚡ Co to umí

- Napojení na **více WooCommerce shopů** přes REST API
- Stahování a agregace **objednávek ze všech shopů**
- Zobrazení:
  - celkových tržeb
  - počtu objednávek
  - statistik pro každý shop zvlášť
- Podpora **více měn** (automaticky rozdělené)
- Detail objednávky (produkty, množství atd.)
- Filtrování objednávek podle **stavů**

👉 Už žádné přihlašování do každého shopu zvlášť ani kontrola e-mailů.

---

## 🔥 Hlavní vlastnosti

- 🧩 Podpora **stovek WooCommerce shopů**
- ⚡ Přírůstkové aktualizace (po prvním načtení rychlé)
- 🔄 Automatická kontrola změn stavů (posledních 30 dní)
- 💾 Není potřeba databáze
- 🖥️ Jednoduché webové rozhraní s administrací
- 🧠 Cache + možnost ručního obnovení
- 🌍 **Multijazyčné rozhraní** (snadno rozšiřitelné)

---

## 🧱 Požadavky

- PHP **7.4 nebo vyšší**
- WooCommerce REST API (consumer key & secret)
- Web server (Apache/Nginx)

---

## ⚙️ Jak to funguje

### 1. První načtení

- Stáhne všechny objednávky ze všech shopů
- Může trvat déle podle množství dat

### 2. Další aktualizace

- Stahuje jen nové objednávky
- Kontroluje změny stavů za posledních 30 dní

---

## 🔄 Cron

Aktualizace probíhá přes cron:

```bash
https://your-domain.tld/cron.php?token=YOUR_SECRET_TOKEN
```

👉 Doporučeno spouštět každou hodinu

---

## ⚙️ Konfigurace

Základní nastavení je v `config.php`:

- nastavení **admin hesla** (pro přístup do aplikace)
- nastavení **cron tokenu**
- nastavení intervalu cache

👉 WooCommerce shopy se **nenastavují v souborech**.

Po přihlášení do administrace:
- přidáváš shopy přes formulář (URL + API klíče)
- spravuješ všechny shopy pohodlně na jednom místě

---

## 🖥️ Použití

- otevři aplikaci v prohlížeči
- přihlas se
- sleduj agregovaná data ze všech shopů
- filtruj objednávky podle stavů
- kliknutím zobraz detail objednávky

---

## 💡 Proč to používat?

- nemusíš se přihlašovat do každého WooCommerce zvlášť
- žádný WordPress plugin
- rychlé a jednoduché řešení
- přehled všech shopů na jednom místě

---

## 🌍 Podpora jazyků

Aplikace obsahuje tyto jazyky:

- cs, de, el, en, es, fr, hu, it, nl, pl, ro, ru, sk

👉 Další jazyk lze jednoduše přidat překladem souboru `en.php`.

---

## ⚠️ Poznámky

- první načtení může trvat déle
- je nutné mít správně n

---

# 🇬🇧 English version

Lightweight PHP application for aggregating and monitoring orders across multiple WooCommerce stores — all in one place.

---

## ⚡ What it does

- Connects to **multiple WooCommerce stores** via REST API
- Fetches and aggregates **orders from all stores**
- Displays:
  - total revenue
  - number of orders
  - per-store statistics
- Supports **multiple currencies**
- Detailed order view
- Filtering by order status

---

## 🔄 Cron

```bash
https://your-domain.tld/cron.php?token=YOUR_SECRET_TOKEN
```

---

## ⚙️ Configuration

- Admin password in `config.php`
- Stores are added via web interface

---

## 📄 License

MIT License

