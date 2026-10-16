# OneCar — React + PHP + MySQL practical demo

This project keeps the existing OneCar premium landing-page copy, imagery, vehicle cards, and visual direction, and adds a map-based booking workflow, road-distance pricing, a PHP/MySQL API, simulated payments, and an authenticated admin workspace. It is an educational demonstration: it does **not** dispatch drivers or move real money.

## Included

- React + Vite frontend and responsive existing OneCar landing page.
- Leaflet + OpenStreetMap map with pickup/destination markers and map-click selection.
- Nominatim address search and OSRM road-route distance/duration (public services have usage limits; do not treat them as production infrastructure).
- Fare estimate by vehicle category, road distance, and City/Airport/Outstation surcharge.
- PHP API that recalculates fares server-side, validates future pickup time, and stores customers/bookings/payments in MySQL.
- Cash-to-driver record and simulated online payment. Simulated payments are explicitly marked as demo and never charge money.
- Admin login, booking status management, fleet management, pricing editor, customer list, payment ledger, and driver-earnings/commission records.
- Browser-only local demo fallback if the API is unavailable. Local fallback records are stored only in that browser and are **not visible to the server/admin dashboard**.

## A. Run the React frontend

Requirements: Node.js LTS and npm.

```bash
cd onecar-react
npm install
copy .env.example .env
npm run dev
```

Open the Vite URL (usually `http://localhost:5173`). Map tiles, geocoding, and routes require an internet connection. If you only want to preview the frontend, it can still run with the API disconnected; bookings then use browser-only demo storage by default.

Build check:

```bash
npm run build
npm run preview
```

## B. Run the PHP + MySQL backend using XAMPP on Windows

1. Start **Apache** and **MySQL** in XAMPP.
2. Copy this project's `backend` folder to `C:\xampp\htdocs\onecar-backend` so the API file is `C:\xampp\htdocs\onecar-backend\public\api\index.php`.
3. In phpMyAdmin, import `database/onecar.sql`. It creates/uses a database named `onecar` and sample fare rules/vehicles.
4. Copy `backend/config.example.php` to `backend/config.php` (inside the copied `onecar-backend` folder). For default XAMPP MySQL, `root` with a blank password is commonly used locally; change these values if your setup differs. Never use a blank database password on a public server.
5. Ensure Apache `mod_rewrite` and `.htaccess` overrides are enabled. Visit `http://localhost/onecar-backend/public/api/health`; it should return JSON with `"ok": true`.
6. From the project root, create an admin account. Use a password of at least 12 characters:

```bat
php backend/scripts/create_admin.php admin@example.com "Use-A-Long-Unique-Password" "OneCar Admin"
```

Run this command from a terminal where PHP is on PATH (XAMPP PHP is typically `C:\xampp\php\php.exe`). If PHP isn't on PATH, run `C:\xampp\php\php.exe backend\scripts\create_admin.php ...` from the project root. Do not commit the password or `backend/config.php`.
7. Keep `.env` set to `VITE_API_BASE_URL=http://localhost/onecar-backend/public/api` and restart Vite after editing environment variables.
8. Open `http://localhost:5173/admin/login` and use the admin email/password you created. The public customer booking form is at `/`.

### If your API URL is different

Set `VITE_API_BASE_URL` to the full base URL ending in `/api`, without a trailing slash, then restart/rebuild the frontend. `FRONTEND_ORIGIN` in `backend/config.php` must exactly match the frontend origin for credentialed admin requests (including protocol and port).

## C. Database and fare rules

Edit base fare, per-kilometre rate, and Airport/Outstation add-ons in the Admin → Pricing page. The customer UI requests the pricing config for display, but the PHP API recalculates the actual stored fare using the MySQL rule, rather than trusting the fare sent by the browser.

Seed demonstration rates (change in Admin → Pricing):

- Comfort Sedan: NPR 100 base + NPR 40/km
- Premium SUV: NPR 180 base + NPR 58/km
- Executive Class: NPR 300 base + NPR 80/km
- Airport and Outstation surcharges are separate configurable amounts.

These are invented sample rates for learning, not real OneCar quotes. A route provider returns an estimated road distance and duration; it does not know OneCar's business pricing. The public OSRM and Nominatim services have fair-use policies and are not a production SLA.

## D. Demo payment and commission workflow

- Cash: creates a payment record with `pending_cash`; the demo does not verify that cash was physically handed over.
- Simulated online: creates a demo booking and calls `/payments/simulate`; it marks a transaction `paid_demo`. No eSewa/Khalti account is charged.
- Admin can confirm/cancel/complete bookings. Completing a booking creates a sample driver-earnings record and calculates a 15% demo platform commission.
- The database includes refunds and payouts tables for future exercises, but real refunds and driver bank transfers are intentionally **not** implemented.

Do not represent `paid_demo`, simulated refunds, or earnings records as real payments or real money held by OneCar.

## E. Deploying for a portfolio demo

### Frontend

Deploy `frontend` only if you later split the directory; in this repository, deploy the project root to Vercel, use the Vite preset, `npm run build`, and output directory `dist`. Set `VITE_API_BASE_URL` to the deployed API's HTTPS `/api` URL and `VITE_DEMO_FALLBACK=true` if you want local-only demo fallback to remain available. Vite environment variables are public, so never put database secrets in them.

### PHP API

`backend/Dockerfile` provides a basic Apache/PHP container suitable for a Docker-capable host such as Render. Configure environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `FRONTEND_ORIGIN`, `SESSION_SECURE=1`, and `SESSION_SAMESITE=None` for a cross-site HTTPS frontend/API deployment. Import `database/onecar.sql` into a reachable MySQL server separately. A Docker container alone does not supply MySQL. Free hosting tiers and free databases change, so check current limits before deploying.

If frontend and API are on different sites, browsers may restrict cross-site cookies. For admin sessions, use HTTPS, `SESSION_SECURE=1`, `SESSION_SAMESITE=None`, exact CORS origin, and test browser cookie behavior. For a production deployment, add CSRF protection, rate limiting, logging/monitoring, secure secrets management, stricter CORS, and a deployment-specific security review.

## Important limitations

- This is a learning MVP, not a production ride-hailing system.
- No live driver dispatch, GPS tracking, OTP login, real card/wallet gateway, real refunds, or bank payouts.
- Admin authentication is server-side; do not add a public admin registration route.
- Public OpenStreetMap tiles, Nominatim, and OSRM are community services with usage limits. For heavy use, use a provider with an appropriate plan or self-host routing/geocoding.
- Before real payments, complete merchant onboarding and follow the selected provider's official integration and verification requirements. Never trust a browser redirect as proof of payment.
