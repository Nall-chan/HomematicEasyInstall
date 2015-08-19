# HomeMatic-EasyInstall

**Version 1.49**  

Dieses Script legt alle noch nicht vorhandenen Homematic-Geräte in IPS an.  

## Vorraussetzungen:
1 CCU1 oder CCU2  
2 min. IPS Pro (es werden jede Menge Variablen angelegt)  
3 Die Geräte müssen in der CCU nach Räumen geordnet sein. Diese Strucktur wird in IPS nachgebildet. Es wird jedoch nur ein Raum pro Gerät unterstützt.  
4 Die Geräte sollten in der CCU nach Gewerken sortiert sein. Diese Strucktur wird in IPS mit Links nachgebildet.  
5 Firewall in der CCU muss so konfiguriert sein, das IPS Zugriff auf die ReGa HSS Logikschicht hat.
6 Je CCU muss auf dem IPS System eine eventuelle Firewall so eingerichtet werden, dass die CCU IPS auf den Port 5544 (fortlaufend für jede CCU ein Port) erreichen kann.  
7 Alle Bezeichnungen in der CCU dürfen keines der folgenden Zeichen enthalten: <, >, ', ", &, $, [, ], {, } und \  Dies ist aber schon Vorgabe von der CCU ! Wer es dennoch schafft solche Zeichen einzugeben, muss mit Fehlern (auch auf der CCU!) rechnen.

## Verwendung:  

1 Es muss wenigstens im Abschnitt Konfiguration eine CCU eingetragen werden.  
2 Zusätzliche Einstellungen sind jeweils seperat beschrieben.  
3 Das Script ausführen.  

## Hinweise:  
 Sollte das Script mit einem Fehler beendet werden; einfach noch mal  
 ausführen. Bereits angelegte Geräte werden übersprungen!
