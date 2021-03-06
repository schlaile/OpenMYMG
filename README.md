OpenMYMG

Generische Schnittstelle, basiert auf dem offenen Teil der
MYMG-Schnittstelle (Meyer Yamaha Meinl GEWA) Dateiaustauschspezifikation.

Sinn der �bung:

Aktuell ist zwar die Preisliste in ihrem Aufbau definiert, jedoch gibt es
keine einheitliche API, um diese von einem Lieferanten abzuholen.

Um die Implementierung auf allen Seiten m�glichst einfach zu halten,
w�re ein �bergang zu einer REST-basierten API w�nschenswert.

Details, warum das sinnvoll ist, findet sich unter 
[als Foliensatz](docs/OpenMYMG.pdf).

Beim jeweiligen Lieferanten ist nur eine einfache Implementierungsdatei
an Hand der Beispiel-Implementierung auszuf�llen, danach kann in 
einheitlicher Weise

* der aktuelle Datenbestand abgefragt werden
* ein Artikel-Status abgefragt werden
* Bestellungen aufgegeben werden
* Lieferavise abgerufen werden
* Originaldokumente im PDF-Format abgerufen werden

Innerhalb der API kann kommuniziert werden, wieviele Elemente der Norm
auch implementiert werden.

Dadurch kann schrittweise zun�chst der Artikel-Stammexport und danach
sukzessive Bestell-Einreichung und Lieferavis implementiert werden.

Um die Installation m�glichst einfach zu halten, ist die Implementierung
in PHP5 ohne weitere Abh�ngigkeiten gehalten.

Sollten f�r den Connector zum Haussystem dann Datenbank-Schnittstellen
ben�tigt werden, m�ssen diese nat�rlich entsprechend installiert werden.

Was allerdings dank PHP-PDO kein Problem sein sollte.

Es spricht nichts dagegen, aus der internen API-Spezifikation auch
automatisch einen SOAP-API Endpunkt generieren zu lassen.

Gegen einen EDIFACT-Postkorb spricht ebenfalls grunds�tzlich nichts, wobei
dieser vermutlich nur dann Sinn macht, falls eine entsprechende Infrastruktur
bereits vorhanden ist.

Die Schnittstelle steht, auch auf Grund der verwendeten EDIFACT und
SOAP-Bibliotheken unter der GNU General Public License 3.0 und kann
daher beliebig kopiert und intern weiter verwendet werden.

Ein Weiterverkauf ist dadurch leider in geschlossener Form ausgeschlossen,
jedoch grunds�tzlich m�glich - der Quellcode und die Lizenzdateien m�ssen
entsprechend dem jeweiligen Kunden zur Verf�gung gestellt werden.

Peter Schlaile