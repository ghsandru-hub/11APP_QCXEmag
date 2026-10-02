# QCX eMag

Aplicația smartBIZ Copilot pentru deconturile eMag Marketplace, cu backend PHP și SQLite.

Build curent: `2026.10.02.1`, publicat la https://qcxemag.aiall.ro/.

## Structură și publicare

- `public_mkpemag/`: fișierele aplicației, publicate în `/home/aiallro/public_mkpemag`.
- `private_mkpemag/data/`: date persistente în `/home/aiallro/private_mkpemag/data`; repository-ul conține doar protecția `.htaccess`.

Bazele de date existente nu se suprascriu la publicare. Păstrați un backup al fișierelor de cod înainte de actualizare.

## Import Excel și tipuri de factură noi

Tipurile de factură absente din nomenclator se adaugă automat o singură dată. Facturile sunt păstrate și pot fi procesate în DataSet, cu mapările contabile necompletate. Importul, Nomenclatorul și rezultatul procesării semnalează codurile de actualizat.

Completați mapările contabile și TVA în Nomenclator, salvați, apoi re-procesați DataSet. Până la completarea mapării, calculul TVA folosește cota din documentul importat.

## Verificări pentru buildul curent

- Sintaxa tuturor scripturilor JavaScript și teste pentru tipuri noi, deduplicare, mapări existente și TVA RON/valută.
- `php -l public_mkpemag/index.php` pe server.
- Verificarea versiunii online, ecranului de import, nomenclatorului, modalului de editare și temelor light/dark, fără erori JavaScript observate.
