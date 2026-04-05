![Dashboard](/docs/logo-woodashboard-500.png)

# 🚀 WooDashboard

Přehled všech WooCommerce objednávek na jednom místě.

## 📸 Ukázka

> Kompletní přehled všech objednávek napříč WooCommerce shopy

### Dashboard
![Dashboard](/docs/dashboard.png)

### Objednávky & Detail
![Orders](/docs/orders.png)

### Správa shopů
![Settings](/docs/settings.png)

### Přidání nového shopu
![Add store](/docs/add-store.png)

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
- je nutné mít správně nastavený cron

---

# 🇬🇧 WooDashboard

All your WooCommerce orders in one place.

## 📸 Screenshots

> Complete overview of all orders across WooCommerce shops

### Dashboard
![Dashboard](/docs/dashboard.png)

### Orders & Detail
![Orders](/docs/orders.png)

### Store Management
![Settings](/docs/settings.png)

### Add new store
![Add store](/docs/add-store.png)

---

## ⚡ What it does

- Connects to **multiple WooCommerce stores** via REST API
- Fetches and aggregates **orders from all stores**
- Displays:
  - total revenue
  - number of orders
  - per-store statistics
- Supports **multiple currencies** (automatically separated)
- Provides **detailed order view** (products, quantities, etc.)
- Allows filtering orders by **status**

👉 No more logging into multiple admin panels or checking emails manually.

---

## 🔥 Key Features

- 🧩 Supports **hundreds of WooCommerce stores**
- ⚡ Incremental updates (fast after first sync)
- 🔄 Automatic status updates (last 30 days)
- 💾 No database required
- 🖥️ Simple web interface with administration
- 🧠 Smart caching with manual refresh option
- 🌍 Multilingual support (easy to extend)

---

## 🧱 Requirements

- PHP **7.4 or higher**
- WooCommerce REST API access (consumer key & secret)
- Web server (Apache/Nginx)

---

## ⚙️ How it works

### 1. Initial sync

- Downloads all orders from all configured stores
- May take longer depending on volume

### 2. Incremental updates

- Fetches only new orders
- Checks status changes for last 30 days

---

## 🔄 Cron job

```bash
https://your-domain.tld/cron.php?token=YOUR_SECRET_TOKEN
```

👉 Recommended: run every hour

---

## ⚙️ Configuration

Basic setup is done in `config.php`:

- Set admin password
- Set cron token
- Configure cache refresh interval

👉 WooCommerce stores are NOT configured in files.

After logging into the web interface, you can:
- Add stores via a simple form (API URL, keys)
- Manage all connected stores in one place

---

## 🖥️ Usage

- Open the application in browser
- Log in
- View aggregated data across all stores
- Filter orders by status
- Click any order to see details

---

## 🌍 Languages

Included languages:

- cs, de, el, en, es, fr, hu, it, nl, pl, ro, ru, sk

👉 You can easily add new language by translating `en.php`.

---

## 💡 Why use this?

- No need to log into multiple WooCommerce admin panels
- No dependency on WordPress plugins
- Lightweight and fast
- Centralized overview of all your stores

---

## 👤 Author

Created by **Fany VanDaal** & **MaDaNo**  
https://madano.cz

Feel free to use, modify or contribute 🚀

## 📄 License

This project is free to use, but selling it or redistributing it as a paid product is not allowed.