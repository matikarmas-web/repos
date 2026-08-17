# Fault-tingimused andmebaasist (Ignition Perspective)

Tootmisliinid (`line`) ja operatsioonid (`code`) tulevad ja lähevad. Nende **lisatingimused** ei ela enam Perspective skriptis `if line==...` puuna, vaid tabelis. Skript loeb avaldise ja käivitab selle `eval`-iga.

## Mida tabelisse panna

Ainult **tõeväärtusavaldis**, mitte tervet `if`-lauset.

| line     | code | condition                                              |
|----------|------|--------------------------------------------------------|
| PROM1004 | VA   | `radio == 'na' and 'silence' in code_text`             |
| PROM1004 | VA   | `radio != 'na' and 'silence' not in code_text`         |
| PROM2001 | XB   | `'manual' in ui('row3', 'mode')`                       |
| PROM2001 | XB   | *(tühi)* — rida kehtib alati                           |

Avaldises on kasutusel:

- `row` — praegune andmerea dict
- `line`, `code`, `radio` — otseteed
- `code_text` — `row2/code` välja tekst, väiketähtedeks
- `ui('row2', 'code')` — suvalise lapse `props.text` (väiketähtedeks)

Ära pane tabelisse `self.parent.parent.getChild(...)`. See jääks tavaliseks sõneks ega loeks tegelikku UI väärtust. Kasuta `code_text` või `ui(...)`.

Jythonis kasuta `!=`, mitte `<>`. Sõned pane jutumärkidesse: `'na'`, mitte `na`.

## Paigaldus Ignitionisse

1. Loo tabel (`sql/fault_conditions.sql`) ja Named Query `fault_conditions_for_line`.
2. Kopeeri `fault_eval.py` Project Library moodulisse (nt `fault_eval`).
3. Asenda vana `if line=='PROM1004':` puu näitega `ignition/perspective_example.py` (`extra_fault_text`).

Uue liini või operatsiooni lisamiseks: INSERT tabelisse. Koodi muutma ei pea.

Click-skriptis on `extra_fault_text` **lisafault**: kui selle `line`+`code` kohta **pole reeglit**, ei lisata midagi. Mitu reeglit samale paarile on **VÕI** (piisab ühest tõesest, nagu kaks eraldi `if`-i). `must` / `mode` / `notSelected` / `not ok` loogika jääb skripti.

Kui tahad, et tabel ise oleks tõde (iga rida kannab oma `condition` veergu), anna `collect_faults(..., rules=[])` — siis loetakse tingimus andmerealt.

## Turve

`eval` on piiratud (`open` / `__import__` ei ole saadaval, `__` nimesid ei lubata), aga **andmebaasi kirjutaja määrab ikkagi loogika**. See on mõeldud teie enda inseneridele, mitte suvalisele kasutajale. Sintaksiviga reeglis logitakse ja see rida jäetakse vahele — ülejäänud fault-tekst ehitatakse edasi.

## Testid (ilma Ignitionita)

```bash
python -m unittest tests.test_fault_eval
```
