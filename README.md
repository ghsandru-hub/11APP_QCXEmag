# QCX eMag

Aplicația smartBIZ Copilot pentru deconturile eMag Marketplace, cu backend PHP și SQLite.

Build local: `2026.10.04.1`. Aplicația online: https://qcxemag.aiall.ro/.

Ultimul deploy verificat: `2026.10.02.1`; noul build necesită publicarea celor trei fișiere din `public_mkpemag`: `index.php`, `configurable-data-table.js` și `configurable-data-table.css`.

## Structură și publicare

- `public_mkpemag/`: fișierele aplicației, publicate în `/home/aiallro/public_mkpemag`.
- `private_mkpemag/data/`: date persistente în `/home/aiallro/private_mkpemag/data`; repository-ul conține doar protecția `.htaccess`.

Bazele de date existente nu se suprascriu la publicare. Păstrați un backup al fișierelor de cod înainte de actualizare.

## Import Excel și tipuri de factură noi

Tipurile de factură absente din nomenclator se adaugă automat o singură dată. Facturile sunt păstrate și pot fi procesate în DataSet, cu mapările contabile necompletate. Importul, Nomenclatorul și rezultatul procesării semnalează codurile de actualizat.

Completați mapările contabile și TVA în Nomenclator, salvați, apoi re-procesați DataSet. Până la completarea mapării, calculul TVA folosește cota din documentul importat.

## Verificări pentru buildul publicat 2026.10.02.1

- Sintaxa tuturor scripturilor JavaScript și teste pentru tipuri noi, deduplicare, mapări existente și TVA RON/valută.
- `php -l public_mkpemag/index.php` pe server.
- Verificarea versiunii online, ecranului de import, nomenclatorului, modalului de editare și temelor light/dark, fără erori JavaScript observate.

## Tabele detaliate configurabile

Comportamente adaptate din `Time-Mobility-Tracker/src/shared/components/ConfigurableDataTableScreen.tsx`, integrate în structura PHP/JavaScript existentă. Se aplică în Date eMag (raw), DataSet, Nomenclator, Marketplaces, Curs Valutar și Jurnal contabil. Raportul dinamic păstrează configurarea și pivotarea existente.

- Sortare multiplă (Shift + clic), filtre de text, numere și date, inclusiv gol/completat.
- Afișare, ordine, lățime, fixare, tip, aliniere și zecimale pentru coloane.
- Grupare, sumă, medie, minim, maxim și număr valori; sumele în valută sunt separate pe monede.
- Selecție peste pagini, detalii, export Excel/CSV, print/PDF și grafic din rezultatul filtrat sau selecția curentă.
- Configurații salvate în browser, separat pentru utilizator și tabel. Datele și permisiunile contabile rămân neschimbate.

Verificare: `node --check public_mkpemag/configurable-data-table.js` și `node tests/configurable-data-table.test.cjs`. Testul de integrare folosește Node.js și `jsdom`; instalați această dependență de test sau indicați un proiect care o conține prin `QCX_TEST_NODE_MODULES`. Testele folosesc exclusiv date sintetice, fără acces la baze persistente.

Pentru buildul 2026.10.04.1 au trecut verificarea sintaxei JavaScript, `git diff --check` și testele de integrare DOM. Verificarea vizuală în browser nu a putut rula în sesiunea de implementare (instrumentul de browser nu pornește); rămâne necesară înainte de deploy.
