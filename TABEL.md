# Tabele smartBIZ — cerințe și comportament

Model de referință: `Time-Mobility-Tracker/src/shared/components/ConfigurableDataTableScreen.tsx`.

## Configurare și aspect

- Păstrează identitatea smartBIZ, temele light/dark și utilizarea pe desktop și mobil.
- Toate câmpurile, inclusiv căutarea din configurator, folosesc stilurile UI smartBIZ.
- Configurează afișarea, ordinea, fixarea, lățimea, tipul, alinierea și zecimalele coloanelor.
- O coloană nou fixată se mută după ultima coloană fixată.
- Lățimea poate fi redusă până la zero, indiferent de conținut, prin tragerea marginii, tastele săgeată pe mâner sau câmpul numeric din configurator.
- La zero, coloana dispare din tabel. Se poate readuce din configurator cu o lățime pozitivă și afișarea bifată. Conținutul nu impune o lățime minimă.
- Configurațiile tabelelor detaliate se salvează separat pentru utilizator și tabel.

## Grupare

- Permite gruparea pe mai multe coloane, în ordinea aleasă.
- Coloanele de grupare apar pe o singură linie deasupra antetului tabelului.
- Coloana grupată dispare din coloanele de date; valoarea rămâne în antetul grupului și în export.
- Antetele grupurilor rămân fixate la derulare.
- Fiecare grup permite restrângere și extindere.
- În stânga antetului există un buton pentru restrângerea/extinderea tuturor grupurilor.
- Restrângerea afectează afișarea, fără să elimine date din totaluri sau export.

## Coloane virtuale din date

- Configuratorul permite alegerea unei coloane de dată și adăugarea An, Lună sau ambelor.
- An este anul calendaristic; Lună este numărul lunii, de la 1 la 12.
- Valorile se calculează din coloana sursă, fără modificarea datelor importate.
- Datele lipsă sau invalide produc valori goale.
- Coloanele calculate permit afișare, filtrare, sortare, grupare și export.
- Definițiile se păstrează în configurațiile salvate. Adăugarea repetată nu dublează coloanele.
- În Raport dinamic coloanele calculate coexistă cu câmpurile An/Luna deja existente.

## Acțiuni și detalii

- Butoanele info ale tabelelor deschid popup modal cu închidere și restaurarea focusului.
- În Raport dinamic, butonul i al paginii rămâne funcțional după reconstruirea configuratorului.
- Informațiile despre filtrele active se deschid în modal; eliminarea unui filtru sau a tuturor filtrelor actualizează raportul.
- Detaliile unui rând se deschid în modal; acțiunile existente de editare se păstrează.

## Funcții păstrate

- Sortare multiplă, filtre pentru text/numere/date și filtre gol/completat.
- Agregări sumă, medie, minim, maxim și număr; totalurile în valută sunt separate pe monede.
- Selecție peste pagini, export Excel/CSV, print/PDF și grafic pe rezultatul filtrat sau selecție.
- Tabele detaliate: Date eMag raw, DataSet, Nomenclator, Marketplaces, Curs Valutar și Jurnal contabil.
- Raport dinamic păstrează motorul său existent de configurare și pivotare.

## Import Excel

- TIP FACTURA absent din nomenclator nu blochează importul liniei.
- Tipul nou se adaugă o singură dată în nomenclator și se semnalează necesitatea completării mapărilor.
- După actualizarea nomenclatorului, DataSet se reprocesează.

## Verificare

Teste cu date sintetice în `tests/configurable-data-table.test.cjs`, fără modificarea bazelor persistente. Actualizarea codului publicabil include actualizarea buildului.
