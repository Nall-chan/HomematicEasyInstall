<?

# HomeMatic EasyInstall
################################################################################
# Version : 1.50
# Datum : 19.11.2017
# Author: Michael Tröger (mt@neo-ami.de)
#
# Beschreibung:
#
# Dieses Script richtet automatisch alle HM-Geräte in IPS ein.
#
# Vorraussetzungen:
# - min. IPS Pro (es werden jede Menge Variablen angelegt)
# - CCU1 oder CCU2 (Windows-BidCos-Dienst wird nicht unterstützt)
# - Firewall in der CCU muss so konfiguriert sein, das IPS Zugriff
#   auf die ReGa HSS Logikschicht hat.
# - Je CCU muss auf dem IPS System eine eventuelle Firewall so eingerichtet werden,
#   dass die CCU IPS auf den Port 5544 (fortlaufend für jede CCU ein Port) erreichen kann.
# - Alle Bezeichnungen in der CCU dürfen keines der folgenden Zeichen enthalten:
#   <, >, ', ", &, $, [, ], {, } und \
#  Dies ist aber schon Vorgabe von der CCU ! Wer es dennoch schafft solche Zeichen einzugeben
#  muss mit Fehlern (auch auf der CCU!) rechnen.
#
# Verwendung:
#
# - Es muss wenigstens im Abschnitt Konfiguration eine CCU eingetragen werden.
# - Zusätzliche Einstellungen sind jeweils seperat beschrieben.
# - Das Script ausführen.
#     Sollte das Script mit einem Fehler beendet werden; einfach noch mal
#		ausführen. Bereits angelegte Geräte werden übersprungen!
#     Getestet mit ein recht umfangreichen Installation. Es waren am Ende über
#     2000 neue Variablen entstanden.
#
# ChangeLog:
#
# 19.11.2017
#  Neu:     Homematic-IP wird unterstützt (sofern IPS-Version paßt !)
#  Neu:     Verlinkung auf Gewerke (GewerkCat) kann deaktiviert werden.
#  Neu:     Erstellen von Hilfsvariablen und angepaßten Aktions-Scripten kann
#           deaktiviert werden. (ScriptCat)
#  Bugfix:  Es wurden Variablen falsch benannt und Fehler im Script erzeugt,
#           wenn das Mapping unvollständig war (z.B. die ganzen TODO Einträge) 
#
# 20.04.2017
# Bugfix:   CURL-Sendet einen Header welche die CCU nicht unterstützt.
#
# 20.08.2015
#  Neu:     Diverse Geräte-Typen und Kanäle im Mapping ergänzt. Teilweise
#           nur vorbereitet. Es fehlen bei den neuen Geräten noch viele Profile.
#           Im Mapping an dem Kommentar  // TODO zu erkennen.
#
# 29.07.2015
# Bugfix:   Auf der CCU2 sind alle internen Interface-IDs der BidCos-Dienste
#           nicht fest vergeben. Außer dem immer vorhandenen BidCos-RF.
#           Wired-Geräte wurden darum nicht immer zuverlässig angelegt.
#
# 26.07.2015
# Bugfix:   Enthält ein Kanal oder Gerät ein < oder > Zeichen im Namen,
#           schlug das Laden der Daten von der CCU fehl.
#
# 02.07.2015
#  Neu:     Zuätzliche Variablen und Aktions-Scripte für die einfache Bedienung
#           von bestimmen Geräten implementiert:
#					- DIMMER & VIRTUAL_DIMMER
#
# Bugfix:   Alle veralteten IPS_* PHP-Funktionen entfernt.
#           Kleiner Anpassungen, damit das Script auf IPS 4.x besser läuft.
#
# 30.06.2015
#	Neu:     Mehr Profile (Neue Wand & Heizkörperthermostaten, BLIND-Geräte)
#           Zuätzliche Variablen und Aktions-Scripte für die einfache Bedienung
#           von bestimmen Geräten implementiert:
#					- Neue Wand & Heizkörperthermostaten
#					- BLIND-Geräte (Jalousie-Aktoren)
#
#  BugFix:	Die neuen Wand & Heizkörperthermostaten senden einige Statusvariablen
#           erst nach einiger Zeit oder wenn der Modus umgeschaltet wurde.
#           Ein neues Feld 'forceDP' im Mapping-Array erzwingt jetzt das Anlegen
#           von Statusvariablen und fragt anschließend den Wert aus der CCU ab.
#
# ToDo:
#
# Version 1.5 : Fertige Aktions-Scripte für...
#					 die Party Modi-Umschaltung der Thermostaten.
#               ....
#
#

# Konfiguration (1.CCU):
$HMCcuAddress[] = 'x.x.x.x'; // IP oder DNS Name
# Konfiguration (2.CCU):
//$HMCcuAddress[] = 'ccu'; // IP oder DNS Name

# Konfiguration IPS:
$OhneRaum = true; // true = alle Geräte anlegen, auch wenn kein Raum zugeordnet wurde.
                  // false = nur Geräte anlegen, welche in der CCU einem Raum zugeordnet wurden.
$MitStatusmeldungen= true;   // true = Auch MAINTENANCE Kanäle anlegen.
                             // false = Keine MAINTENANCE Kanäle anlegen.
$RaumCat = 0; // ID für Kategorie der Räume  // 0 = neu anlegen oder vorhandene im root suchen
$GewerkCat = 0; // ID für Kategorie Gewerke  // 0 = neu anlegen oder vorhandene im root suchen // -1 = für nicht anlegen
$GewerkTyp = 1; // Gewerke anlegen als Kategorie (0) oder als Dummy-Instanz (1)
$ScriptCat = 0; // ID für HomeMatic Aktions-Scripte // 0 = neu anlegen oder im root suchen // -1= für nicht anlegen

// Datenpunkte wo 'Status emulieren' aktiv sein soll.
$Emulate = array('SET_TEMPERATURE', 'SETPOINT', 'LED_STATUS');

# MAPPING
#
# Hier werden die Kanaltypen mit ihren Datenpunkten konfiguriert.
# IPS legt bei vielen Datenpunkten (Variablen) automatisch ein
# richtiges Profil an. Jedoch entsprechen die Namen immer den
# Datenpunkt (z.B. STATE, LEVEL etc...)
#
# Die erste Ebene enthält alle Kanaltypen welche angelegt werden sollen.
# Ist ein Kanaltyp hier nicht angelegt und wird in der CCU gefunden,
# wird er nicht in IPS angelegt und dafür eine Meldung ausgegeben.
#
# Das Array innerhalb der Kanaltypen enthält alle Datenpunkte,
# welche nach den erzeugen in IPS noch weiter bearbeitet werden sollen.
#
# Folgende Felder müssen dafür vorhanden sein:
#
# 'Profil' : IPS kennt nicht für jeden Homematic Datenpunkt das korrekte Profil.
#            Hier kann ein eigenen Profil erzwungen werden.
#
# 'Action' : IPS aktiviert für bestimme Datenpunkte automatisch eine Standardaktion.
#            Leider auch für Geräte welche gar nicht schaltbar sind, wie Rauchmelder
#            oder TKF. Mit false / true kann dieses Verhalten korrigiert werden.
#
# 'Name Raum' = Name der Variable unterhalb vom Gerät.
#               Es stehen folgende Platzhalter zur Verfügung:
#                    %1$s = Name vom Gerät
#                    %2$s = Name vom Raum
#
# 'Name Gewerk' = Name des erzeugen Links innerhalb des Gewerkes, welcher auf die Variable zeigt.
#                 Es stehen folgende Platzhalter zur Verfügung:
#                       %1$s = Name vom Gerät
#                       %2$s = Name vom Raum
#                       %3$s = Name vom Gewerk

$TypMappingProfil = array(
    'VIRTUAL_KEY' => array(),
    'SMOKE_DETECTOR' => array(
        'STATE' => array(
            'Name Raum' => 'Auslösung',
            'Name Gewerk' => '%1$s',
            'Profil' => '~Alert',
            'Action' => false
        )
    ),
    'SMOKE_DETECTOR_TEAM' => array(
        'STATE' => array(
            'Name Raum' => 'Auslösung',
            'Name Gewerk' => '%1$s',
            'Profil' => '~Alert',
            'Action' => false
        )
    ),
    'KEY' => array(), // kein Zuordnung aber anlegen
    'KEY_TRANSCEIVER' => array(), // kein Zuordnung aber anlegen	
    'RAINDETECTOR' => array(
        'STATE' => array(
            'Name Raum' => 'Regen',
            'Name Gewerk' => 'Regen %2$s',
            'Profil' => '~Raining', // ~Raining
            'Action' => false
        )
    ),
    'RAINDETECTOR_HEAT' => array(
        'STATE' => array(
            'Name Raum' => 'Heizung Regensensor',
            'Name Gewerk' => 'Heizung Regensensor %2$s',
            'Profil' => '', // ~Switch
            'Action' => true
        )
    ),
    'WEATHER' => array(
        'TEMPERATURE' => array(
            'Name Raum' => 'Temperatur',
            'Name Gewerk' => 'Temperatur %2$s',
            'Profil' => '', // ~Temperature
            'Action' => false
        ),
        'HUMIDITY' => array(
            'Name Raum' => 'Luftfeuchte',
            'Name Gewerk' => 'Luftfeuchte %2$s',
            'Profil' => '', // ~Humidity
            'Action' => false
        ),
        'RAINING' => array(
            'Name Raum' => 'Regen',
            'Name Gewerk' => 'Regen %2$s',
            'Profil' => '', // ~Raining
            'Action' => false
        ),
        'RAIN_COUNTER' => array(
            'Name Raum' => 'Regenmenge',
            'Name Gewerk' => 'Regenmenge %2$s',
            'Profil' => '', // ~Rainfall
            'Action' => false
        ),
        'WIND_SPEED' => array(
            'Name Raum' => 'Windgeschwindigkeit',
            'Name Gewerk' => 'Windgeschwindigkeit %2$s',
            'Profil' => '', // ~WindSpeed.kmh
            'Action' => false
        ),
        'WIND_DIRECTION' => array(
            'Name Raum' => 'Windrichtung',
            'Name Gewerk' => 'Windrichtung %2$s',
            'Profil' => '', // ~WindDirection
            'Action' => false
        ),
        'WIND_DIRECTION_RANGE' => array(
            'Name Raum' => 'Windrichtungsschwankung',
            'Name Gewerk' => 'Windrichtungsschwankung %2$s',
            'Profil' => '', // ~WindDirection
            'Action' => false
        ),
        'SUNSHINEDURATION' => array(
            'Name Raum' => 'Sonnenscheindauer',
            'Name Gewerk' => 'Sonnenscheindauer %2$s',
            'Profil' => '', // OFFEN
            'Action' => false
        ),
        'BRIGHTNESS' => array(
            'Name Raum' => 'Helligkeit',
            'Name Gewerk' => 'Helligkeit %2$s',
            'Profil' => '', // ~Brightness.HM
            'Action' => false
        ),
        'LOWBAT' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Battery
            'Action' => false
        ),
        'AIR_PRESSURE' => array(
            'Name Raum' => 'Luftdruck',
            'Name Gewerk' => 'Luftdruck %2$s',
            'Profil' => '', // TODO  int hPa
            'Action' => false
        )
    ),
    'ROTARY_HANDLE_SENSOR' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // Window.HM
            'Action' => false
        ),
        'ERROR' => array(
            'Name Raum' => 'Fehler',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // FEHLT
            'Action' => false
        ),
        'LOWBAT' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Battery
            'Action' => false
        )
    ),
	'SWITCH_SENSOR' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '',
            'Action' => true
        ),
        'ERROR' => array(
            'Name Raum' => 'Fehler',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // FEHLT
            'Action' => false
        ),
        'ERROR_SABOTAGE' => array(
            'Name Raum' => 'Sabotage',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // FEHLT
            'Action' => false
        ),		
        'LOWBAT' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Battery
            'Action' => false
        )
    ),
	'SWITCH_PANIC' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '',
            'Action' => true
        ),
        'ERROR' => array(
            'Name Raum' => 'Fehler',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // FEHLT
            'Action' => false
        ),
        'ERROR_SABOTAGE' => array(
            'Name Raum' => 'Sabotage',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // FEHLT
            'Action' => false
        ),		
        'LOWBAT' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Battery
            'Action' => false
        )
    ),
	'ARMING' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand Scharf',
            'Name Gewerk' => '%1$s',
            'Profil' => '',  // FEHLT
            'Action' => false
        ),
        'ERROR_SABOTAGE' => array(
            'Name Raum' => 'Sabotage',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // FEHLT
            'Action' => false
        ),		
        'LOWBAT' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Battery
            'Action' => false
        )
    ),
	'SWITCH_VIRTUAL_RECEIVER' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Switch
            'Action' => true
        )
    ),
    'SWITCH' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Switch
            'Action' => true
        )
    ),
    'DIGITAL_OUTPUT' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Switch
            'Action' => true
        )
    ),
    'MAINTENANCE' => array(
        /* 'RSSI_DEVICE'
          'CONFIG_PENDING'
          'UNREACH' */
        'LOWBAT' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Battery
            'Action' => false
        )
    /* 'STICKY_UNREACH'
      'RSSI_PEER' */
    ),
    'CLIMATECONTROL_REGULATOR' => array(
        'SETPOINT' => array(
            'Name Raum' => 'Soll Temperatur',
            'Name Gewerk' => 'Soll Temperatur %2$s',
            'Profil' => '', // ~Temperature.HM
            'Action' => true
        )
    ),
    'MOTION_DETECTOR' => array(
        'MOTION' => array(
            'Name Raum' => 'Bewegung',
            'Name Gewerk' => 'Bewegung %2$s',
            'Profil' => '', // ~Motion.HM
            'Action' => false
        ),
        'BRIGHTNESS' => array(
            'Name Raum' => 'Helligkeit',
            'Name Gewerk' => 'Helligkeit %2$s',
            'Profil' => '', // ~Brightness.HM
            'Action' => false
        )
    ),
    'DIMMER' => array(
        'LEVEL' => array(
            'Name Raum' => 'Level',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Intensity.1
            'Action' => true
        )
    ),
    'VIRTUAL_DIMMER' => array(
        'LEVEL' => array(
            'Name Raum' => 'Level',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Intensity.1
            'Action' => true
        )
    ),
    'CLIMATECONTROL_VENT_DRIVE' => array(
        'VALVE_STATE' => array(
            'Name Raum' => 'Ventilöffnung',
            'Name Gewerk' => 'Ventilöffnung %2$s',
            'Profil' => '', // ~Intensity.100
            'Action' => false
        )
    ),
    'SHUTTER_CONTACT' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '~Window', // ~Window
            'Action' => false
        )
    ),
    'SIGNAL_LED' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Switch
            'Action' => true
        )
    ),
    'SIGNAL_CHIME' => array(
        'STATE' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '', // ~Switch
            'Action' => true
        )
    ),
    'BLIND' => array(
        'LEVEL' => array(
            'Name Raum' => 'Level',
            'Name Gewerk' => '%1$s',
            'Profil' => '', // ~Intensity.1
            'Action' => true
        )
    ),
    'SENSOR' => array(
        'SENSOR' => array(
            'Name Raum' => 'Zustand',
            'Name Gewerk' => '%1$s',
            'Profil' => '~Alert',
            'Action' => false
        )
    ),
    'CENTRAL_KEY' => array(), // kein Zuordnung aber anlegen
    'DISPLAY' => array(), // kein Zuordnung aber anlegen
	'ENERGIE_METER_TRANSMITTER' => array(
        'CURRENT' => array(
            'Name Raum' => 'Strom',
            'Name Gewerk' => '%1$s Strom',
            'Profil' => 'mAmpere.F',
            'Action' => false
        ),
        'ENERGY_COUNTER' => array(
            'Name Raum' => 'Gesamter Verbrauch',
            'Name Gewerk' => '%1$s Gesamter Verbrauch',
            'Profil' => 'Wh.F',
            'Action' => false
        ),
        'FREQUENCY' => array(
            'Name Raum' => 'Frequenz',
            'Name Gewerk' => '%1$s Frequenz',
            'Profil' => '~Hertz',
            'Action' => false
        ),
        'POWER' => array(
            'Name Raum' => 'Leistung',
            'Name Gewerk' => '%1$s Leistung',
            'Profil' => '~Watt.3680',
            'Action' => false
        ),
        'VOLTAGE' => array(
            'Name Raum' => 'Spannung',
            'Name Gewerk' => '%1$s Spannung',
            'Profil' => '~Volt',
            'Action' => false
        )
	),
    'POWERMETER' => array(
        'CURRENT' => array(
            'Name Raum' => 'Strom',
            'Name Gewerk' => '%1$s Strom',
            'Profil' => 'mAmpere.F',
            'Action' => false
        ),
        'ENERGY_COUNTER' => array(
            'Name Raum' => 'Gesamter Verbrauch',
            'Name Gewerk' => '%1$s Gesamter Verbrauch',
            'Profil' => 'Wh.F',
            'Action' => false
        ),
        'FREQUENCY' => array(
            'Name Raum' => 'Frequenz',
            'Name Gewerk' => '%1$s Frequenz',
            'Profil' => '~Hertz',
            'Action' => false
        ),
        'POWER' => array(
            'Name Raum' => 'Leistung',
            'Name Gewerk' => '%1$s Leistung',
            'Profil' => '~Watt.3680',
            'Action' => false
        ),
        'VOLTAGE' => array(
            'Name Raum' => 'Spannung',
            'Name Gewerk' => '%1$s Spannung',
            'Profil' => '~Volt',
            'Action' => false
        )
    ),
    'LUXMETER' => array(
        'LUX' => array(
            'Name Raum' => 'Helligkeit',
            'Name Gewerk' => 'Helligkeit %2$s',
            'Profil' => '~Illumination.F', 
            'Action' => false
        )
    ),
    'DIGITAL_ANALOG_OUTPUT' => array(
        'FREQUENCY' => array()                  // TODO float mHz
    ),
    'DIGITAL_INPUT' => array(
        'FREQUENCY' => array()                  // TODO float mHz
    ),
    'DIGITAL_ANALOG_INPUT' => array(
        'VALUE' => array()                      // TODO float
    ),
    'INPUT_OUTPUT' => array(), // nur anlegen
    'POWERMETER_IGL' => array(                  // TODO
        'GAS_ENERGY_COUNTER' => array(),        // TODO m³ float
        'GAS_POWER' => array(),                 // TODO m³ float
        'ENERGY_COUNTER' => array(),            // TODO  Wh float
        'POWER' => array()                      // TODO W float
    ),
    'POWERMETER_IEC1' => array(                 // TODO
        'GAS_ENERGY_COUNTER' => array(),        // TODO m³ float
        'GAS_POWER' => array(),                 // TODO m³ float
        'ENERGY_COUNTER' => array(),            // TODO Wh float
        'IEC_ENERGY_COUNTER' => array(),        // TODO Wh float
        'IEC_POWER' => array(),                 // TODO W float
        'POWER' => array()                      // TODO W float
    ),
    'POWERMETER_IEC2' => array(                 // TODO
        'IEC_ENERGY_COUNTER' => array(),        // TODO Wh float
        'IEC_POWER' => array()                  // TODO W float
    ),	
    'STATUS_INDICATOR' => array(                // TODO
        'STATE' => array()
    ),
    'KEYMATIC' => array(                        // TODO
        'STATE' => array(),                     // OPEN = action RELOCK_DELAY =float write only
        'STATE_UNCERTAIN' => array(),
    ),
    'SENSOR_FOR_CARBON_DIOXIDE' => array(
        'STATE' => array()                      // TODO
                                                # 0 = LEVEL_NORMAL
                                                # 1 = LEVEL_ADDED
                                                # 2 = LEVEL_ADDED_STRONG
    ),
    'ALARMACTUATOR' => array(
        'STATE' => array()                      // TODO
    ),
    'PULSE_SENSOR' => array(),
    'TILT_SENSOR' => array(
        'STATE' => array(
            'Name Raum' => 'Neigung',
            'Name Gewerk' => '%1$s',
            'Profil' => '~Alert',
            'Action' => false
        )
    ),
    'WINMATIC' => array(
        'LEVEL' => array(                       // TODO
        ),
        'STATE_UNCERTAIN' => array(             // TODO bool
        )
    ),
    'AKKU' => array(
        'LEVEL' => array(                       // TODO float
        ),
        'STATUS' => array(                      // TODO int
                                                # 0 = TRICKLE_CHARGE
                                                # 1 = CHARGE
                                                # 2 = DISCHARGE
                                                # 3 = STATE_UNKNOWN
        )
    ),
    'WATERDETECTIONSENSOR' => array(
        'STATE' => array(
            'Name Raum' => 'Wasserstand',
            'Name Gewerk' => '%1$s',
            'Profil' => '',                     // TODO
                                                # 0 = DRY
                                                # 1 = WET
                                                # 2 = WATER
            'Action' => false
        )
    ),
    'CAPACITIVE_FILLING_LEVEL_SENSOR' => array(
        'FILLING_LEVEL' => array(
            'Name Raum' => 'Füllstand',
            'Name Gewerk' => '%1$s',
            'Profil' => '~Intensity.1',
            'Action' => false
        )
    ),
    'WEATHER_TRANSMIT' => array(
        'TEMPERATURE' => array(
            'Name Raum' => 'Temperatur',
            'Name Gewerk' => 'Temperatur %2$s',
            'Profil' => '', // ~Temperature
            'Action' => false
        ),
        'HUMIDITY' => array(
            'Name Raum' => 'Luftfeuchte',
            'Name Gewerk' => 'Luftfeuchte %2$s',
            'Profil' => '', // ~Humidity
            'Action' => false
        )
    ),
    'THERMALCONTROL_TRANSMIT' => array(
        'CONTROL_MODE' => array(
            'Name Raum' => 'Betriebsmodus',
            'Name Gewerk' => 'Betriebsmodus %2$s',
            'Profil' => 'ControlMode.HM', // ~Mode.HM
            'Action' => 'CLIMATECONTROL_SCRIPT'
        ),
        'LOWBAT_REPORTING' => array(
            'Name Raum' => 'Batterie',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '~Battery',
            'Action' => false
        ),
//		'COMMUNICATION_REPORTING'
        'WINDOW_OPEN_REPORTING' => array(
            'Name Raum' => 'Fenster offen',
            'Name Gewerk' => 'Fenster offen %2$s',
            'Profil' => '~Window',
            'Action' => false
        ),
        'BATTERY_STATE' => array(
            'Name Raum' => 'Batteriespannung',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '~Volt',
            'Action' => false
        ),
        'BOOST_STATE' => array(
            'Name Raum' => 'Boost Status',
            'Name Gewerk' => 'Boost Status %2$s',
            'Profil' => '',                                 // TODO
            'Action' => false
        ),
        'ACTUAL_TEMPERATURE' => array(
            'Name Raum' => 'Temperatur',
            'Name Gewerk' => 'Temperatur %2$s',
            'Profil' => '', // ~Temperature
            'Action' => false
        ),
        'ACTUAL_HUMIDITY' => array(
            'Name Raum' => 'Luftfeuchte',
            'Name Gewerk' => 'Luftfeuchte %2$s',
            'Profil' => '~Humidity.F',
            'Action' => false
        ),
        'SET_TEMPERATURE' => array(
            'Name Raum' => 'Soll Temperatur',
            'Name Gewerk' => 'Soll Temperatur %2$s',
            'Profil' => '', // ~Temperature.HM
            'Action' => true
        ),
        'PARTY_TEMPERATURE' => array(
            'Name Raum' => 'Party Temperatur',
            'Name Gewerk' => 'Party Temperatur %2$s',
            'Profil' => '~Temperature',
            'Action' => false
        ),
        'forceDP' => array('PARTY_START_DAY' => 1, 'PARTY_START_MONTH' => 1, 'PARTY_START_TIME' => 1, 'PARTY_START_YEAR' => 1, 'PARTY_STOP_DAY' => 1, 'PARTY_STOP_MONTH' => 1, 'PARTY_STOP_TIME' => 1, 'PARTY_STOP_YEAR' => 1, 'PARTY_TEMPERATURE' => 2)
    ),
    'CLIMATECONTROL_RT_TRANSCEIVER' => array(
        'CONTROL_MODE' => array(
            'Name Raum' => 'Betriebsmodus',
            'Name Gewerk' => 'Betriebsmodus %2$s',
            'Profil' => 'ControlMode.HM', // ~Mode.HM
            'Action' => 'CLIMATECONTROL_SCRIPT'
        ),
        'BATTERY_STATE' => array(
            'Name Raum' => 'Batteriespannung',
            'Name Gewerk' => '', // nicht verlinken
            'Profil' => '~Volt',
            'Action' => false
        ),
        'BOOST_STATE' => array(
            'Name Raum' => 'Boost Status',
            'Name Gewerk' => 'Boost Status %2$s',
            'Profil' => '',                                         // TODO
            'Action' => false
        ),
        'ACTUAL_TEMPERATURE' => array(
            'Name Raum' => 'Temperatur',
            'Name Gewerk' => 'Temperatur %2$s',
            'Profil' => '', // ~Temperature
            'Action' => false
        ),
        'SET_TEMPERATURE' => array(
            'Name Raum' => 'Soll Temperatur',
            'Name Gewerk' => 'Soll Temperatur %2$s',
            'Profil' => '', // ~Temperature.HM
            'Action' => true
        ),
        'PARTY_TEMPERATURE' => array(
            'Name Raum' => 'Party Temperatur',
            'Name Gewerk' => 'Party Temperatur %2$s',
            'Profil' => '~Temperature',
            'Action' => false
        ),
        'VALVE_STATE' => array(
            'Name Raum' => 'Ventilöffnung',
            'Name Gewerk' => 'Ventilöffnung %2$s',
            'Profil' => '', // ~Intensity.100
            'Action' => false
        ),
        'forceDP' => array('PARTY_START_DAY' => 1, 'PARTY_START_MONTH' => 1, 'PARTY_START_TIME' => 1, 'PARTY_START_YEAR' => 1, 'PARTY_STOP_DAY' => 1, 'PARTY_STOP_MONTH' => 1, 'PARTY_STOP_TIME' => 1, 'PARTY_STOP_YEAR' => 1, 'PARTY_TEMPERATURE' => 2)
    //'FAULT_REPORTING' //fehlt
    ),
    'CLIMATE_TRANSCEIVER' => array(
        'ACTUAL_TEMPERATURE' => array(
            'Name Raum' => 'Temperatur',
            'Name Gewerk' => 'Temperatur %2$s',
            'Profil' => '', // ~Temperature
            'Action' => false
        ),
        'HUMIDITY' => array(
            'Name Raum' => 'Luftfeuchte',
            'Name Gewerk' => 'Luftfeuchte %2$s',
            'Profil' => '', // ~Humidity
            'Action' => false
        )
    ),
	'BRIGHTNESS_TRANSMITTER' => array(
        'CURRENT_ILLUMINATION' => array(
            'Name Raum' => 'Aktuelle Helligkeit',
            'Name Gewerk' => '%1$s aktuelle Helligkeit',
            'Profil' => '~Illumination.F',
            'Action' => false
        ),
		'AVERAGE_ILLUMINATION' => array(
            'Name Raum' => 'Durchschnittliche Helligkeit',
            'Name Gewerk' => '%1$s durchschnittliche Helligkeit',
            'Profil' => '~Illumination.F',
            'Action' => false
        ),
		'HIGHEST_ILLUMINATION' => array(
            'Name Raum' => 'Maximale Helligkeit',
            'Name Gewerk' => '%1$s maximale Helligkeit',
            'Profil' => '~Illumination.F',
            'Action' => false
        ),
		'LOWEST_ILLUMINATION' => array(
            'Name Raum' => 'Minimale Helligkeit',
            'Name Gewerk' => '%1$s minimale Helligkeit',
            'Profil' => '~Illumination.F',
            'Action' => false
        ),
	),
	'WATER_DETECTION_TRANSMITTER' => array(
        'WATERLEVEL_DETECTED' => array(
            'Name Raum' => 'Wasserstand erkannt',
            'Name Gewerk' => '%1$s Wasserstand erkannt',
            'Profil' => '~Alert',
            'Action' => false
        ),
		'MOISTURE_DETECTED' => array(
            'Name Raum' => 'Feuchtigkeit erkannt',
            'Name Gewerk' => '%1$s Feuchtigkeit erkannt',
            'Profil' => '~Alert',
            'Action' => false
        ),
	),
	'SHUTTER_TRANSMITTER' => array(
		// ACTIVITY_STATE, LEVEL, LEVEL_STATUS, PROCESS, SECTION, SECTION_STATUS
        'LEVEL' => array(
            'Name Raum' => 'Rolladenhöhe',
            'Name Gewerk' => '%1$s Rolladenhöhe',
            'Profil' => '',
            'Action' => false
        ),
        'ACTIVITY_STATE' => array(
            'Name Raum' => 'Fahraktivität',
            'Name Gewerk' => '%1$s Fahraktivität',
            'Profil' => 'BlindActivity.HM',
            'Action' => false
        ),
	),
	'SHUTTER_VIRTUAL_RECEIVER' => array(
		// ACTIVITY_STATE, LEVEL, LEVEL_STATUS, PROCESS, SECTION, SECTION_STATUS
        'LEVEL' => array(
            'Name Raum' => 'Rolladenhöhe',
            'Name Gewerk' => '%1$s Rolladenhöhe',
            'Profil' => '',
            'Action' => true
        ),
	),
    'SWITCH_INTERFACE' => array() // kein Zuordnung aber anlegen
);

$RequestState = array(
    'STATE',
    'LEVEL',
    'SENSOR',
    'SET_TEMPERATURE',
    'SETPOINT',
    'LOWBAT',
    'HUMIDITY',
    'TEMPERATURE',
    'MOTION',
    'BRIGHTNESS',
    'ACTUAL_HUMIDITY',
    'ACTUAL_TEMPERATURE',
    'BATTERY_STATE',
    'BOOST_STATE',
    'CONTROL_MODE',
    'LOWBAT_REPORTING',
    'WINDOW_OPEN_REPORTING',
    'VALVE_STATE',
    'CURRENT',
    'ENERGY_COUNTER',
    'FREQUENCY',
    'POWER',
    'VOLTAGE',
    'RAINING',
    'RAIN_COUNTER',
    'SUNSHINEDURATION',
    'WIND_DIRECTION',
    'WIND_DIRECTION_RANGE',
    'WIND_SPEED',
    'LED_STATUS',
    'PARTY_TEMPERATURE',
	'LUX',
	'ACTIVITY_STATE'
);

# ENDE Konfig MAPPING



// Nicht Standardprofile anlegen
if (!IPS_VariableProfileExists('Wh.F'))
{
    IPS_CreateVariableProfile('Wh.F', 2);
    IPS_SetVariableProfileDigits('Wh.F', 2);
    IPS_SetVariableProfileText('Wh.F', '', ' Wh');
}

if (!IPS_VariableProfileExists('mAmpere.F'))
{
    IPS_CreateVariableProfile('mAmpere.F', 2);
    IPS_SetVariableProfileDigits('mAmpere.F', 2);
    IPS_SetVariableProfileText('mAmpere.F', '', ' mA');
}

if ($ScriptCat != -1)
{
	if (!IPS_VariableProfileExists('BlindControl.HM'))
	{
	    IPS_CreateVariableProfile('BlindControl.HM', 1);
	
	    IPS_SetVariableProfileAssociation('BlindControl.HM', -1, 'Ab', '', 0x0000FF);
	    IPS_SetVariableProfileAssociation('BlindControl.HM', 0, 'Stop', '', 0x000000);
	    IPS_SetVariableProfileAssociation('BlindControl.HM', 1, 'Auf', '', 0x0000FF);
	}
	if (!IPS_VariableProfileExists('BlindActivity.HM'))
	{
	    IPS_CreateVariableProfile('BlindActivity.HM', 1);

	    IPS_SetVariableProfileAssociation('BlindActivity.HM', 0, 'unbekannt', '', -1);
	    IPS_SetVariableProfileAssociation('BlindActivity.HM', 1, 'aufwärts', '', -1);
	    IPS_SetVariableProfileAssociation('BlindActivity.HM', 2, 'abwärts', '', -1);
	    IPS_SetVariableProfileAssociation('BlindActivity.HM', 3, 'steht', '', -1);
	}
	if (!IPS_VariableProfileExists('ControlTemp.HM'))
	{
	    IPS_CreateVariableProfile('ControlTemp.HM', 1);
	    IPS_SetVariableProfileIcon('ControlTemp.HM', 'Temperature');
	    IPS_SetVariableProfileAssociation('ControlTemp.HM', 1, 'Absenk Temp', '', 0xff0000);
	    IPS_SetVariableProfileAssociation('ControlTemp.HM', 2, 'Komfort Temp', '', 0xff9900);
	}
	if (!IPS_VariableProfileExists('ControlMode.HM'))
	{
	    IPS_CreateVariableProfile('ControlMode.HM', 1);
	    IPS_SetVariableProfileAssociation('ControlMode.HM', 0, 'Automatik', '', 0x339966);
	    IPS_SetVariableProfileAssociation('ControlMode.HM', 1, 'Manuell', '', 0xFF0000);
	    IPS_SetVariableProfileAssociation('ControlMode.HM', 2, 'Urlaub', '', 0x3366FF);
	    IPS_SetVariableProfileAssociation('ControlMode.HM', 3, 'Boost', '', 0xFFFF99);
	}
	if (!IPS_VariableProfileExists('DimmerControl.HM'))
	{
	    IPS_CreateVariableProfile('DimmerControl.HM', 1);
	    IPS_SetVariableProfileIcon('DimmerControl.HM', 'Intensity');
	    IPS_SetVariableProfileAssociation('DimmerControl.HM', -2, '0 %', '', 0x0000FF);
	    IPS_SetVariableProfileAssociation('DimmerControl.HM', -1, 'Dunkler', '', 0x339966);
	    IPS_SetVariableProfileAssociation('DimmerControl.HM', 0, 'Stop', '', 0x000000);
	    IPS_SetVariableProfileAssociation('DimmerControl.HM', 1, 'Zurück', '', 0xFF0000);
	    IPS_SetVariableProfileAssociation('DimmerControl.HM', 2, 'Heller', '', 0x339966);
	    IPS_SetVariableProfileAssociation('DimmerControl.HM', 3, '100 %', '', 0x0000FF);
	}
	if (!IPS_VariableProfileExists('DimmerSpeed.HM'))
	{
	    IPS_CreateVariableProfile('DimmerSpeed.HM', 2);
	    IPS_SetVariableProfileIcon('DimmerSpeed.HM', 'Intensity');
	    IPS_SetVariableProfileValues('DimmerSpeed.HM', 0, 30, 2);
	    IPS_SetVariableProfileText('DimmerSpeed.HM', '', ' s');
	}
	
	
	
	$AddOnMappings = array(
	    'BLIND' => array(
	        'CONTROL' => array(
	            'Name Raum' => 'Steuerung',
	            'Name Gewerk' => 'Steuerung %2$s',
	            'Profil' => 'BlindControl.HM',
	            'Action' => 'BLIND_SCRIPT',
	            'VarTyp' => 1
	        )
	    ),
	    'SHUTTER_VIRTUAL_RECEIVER' => array(
	        'CONTROL' => array(
	            'Name Raum' => 'Steuerung',
	            'Name Gewerk' => 'Steuerung %2$s',
	            'Profil' => 'BlindControl.HM',
	            'Action' => 'BLIND_SCRIPT',
	            'VarTyp' => 1
	        )
	    ),
	    'THERMALCONTROL_TRANSMIT' => array(
	        'CONTROL_TEMP' => array(
	            'Name Raum' => 'Soll Temperatur Vorwahl',
	            'Name Gewerk' => 'Soll Temperatur Vorwahl %2$s',
	            'Profil' => 'ControlTemp.HM',
	            'Action' => 'CONTROL_TEMP_SCRIPT',
	            'VarTyp' => 1
	        )
	    ),
	    'CLIMATECONTROL_RT_TRANSCEIVER' => array(
	        'CONTROL_TEMP' => array(
	            'Name Raum' => 'Soll Temperatur Vorwahl',
	            'Name Gewerk' => 'Soll Temperatur Vorwahl %2$s',
	            'Profil' => 'ControlTemp.HM',
	            'Action' => 'CONTROL_TEMP_SCRIPT',
	            'VarTyp' => 1
	        )
	    ),
	    'DIMMER' => array(
	        'DIMMER_CONTROL' => array(
	            'Name Raum' => 'Steuerung',
	            'Name Gewerk' => 'Steuerung %2$s',
	            'Profil' => 'DimmerControl.HM',
	            'Action' => 'DIMMER_SCRIPT',
	            'VarTyp' => 1
	        ),
	        'RAMP_TIME' => array(
	            'Name Raum' => 'Geschwindigkeit',
	            'Name Gewerk' => 'Geschwindigkeit %2$s',
	            'Profil' => 'DimmerSpeed.HM',
	            'Action' => 'DIMMER_SCRIPT',
	            'VarTyp' => 2
	        )
	    ),
	    'VIRTUAL_DIMMER' => array(
	        'DIMMER_CONTROL' => array(
	            'Name Raum' => 'Steuerung',
	            'Name Gewerk' => 'Steuerung %2$s',
	            'Profil' => 'DimmerControl.HM',
	            'Action' => 'DIMMER_SCRIPT',
	            'VarTyp' => 1
	        ),
	        'RAMP_TIME' => array(
	            'Name Raum' => 'Geschwindigkeit',
	            'Name Gewerk' => 'Geschwindigkeit %2$s',
	            'Profil' => 'DimmerSpeed.HM',
	            'Action' => 'DIMMER_SCRIPT',
	            'VarTyp' => 2
	        )
	    )
	);
}


if ($RaumCat == 0) // neu anlegen oder im root suchen
{
    $RaumCat = GetOrCreateCategoryByName(0, "Räume");
}
else // vorhanden ?
{
    $parent = @IPS_GetObject($RaumCat);
    if ($parent === false)
        die("Manuelle Angabe der ID für Räume fehlerhaft!");
}

if ($GewerkCat == 0) // neu anlegen oder im root suchen
{
    $GewerkCat = GetOrCreateCategoryByName(0, "Gewerke");
}
elseif ($GewerkCat >0) // vorhanden ?
{
    $parent = @IPS_GetObject($GewerkCat);
    if ($parent === false)
        die("Manuelle Angabe der ID für Gewerke fehlerhaft!");
}

if ($ScriptCat == 0) // neu anlegen oder im root suchen
{
    $ScriptCat = GetOrCreateCategoryByName(0, "Aktions-Scripte");
}
elseif ($ScriptCat >0) // vorhanden ?
{
    $parent = @IPS_GetObject($ScriptCat);
    if ($parent === false)
        die("Manuelle Angabe der ID für Scripte fehlerhaft!");
}


ini_set('max_execution_time', count($HMCcuAddress) * 120);

//HM Sockets prüfen und einrichten
$nextPort = 5544;
$HMSockets = array_flip($HMCcuAddress);
$HMSocketsOld = IPS_GetInstanceListByModuleID("{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}");
if (count($HMSocketsOld) > 0)
{
    foreach ($HMSocketsOld as $HMSocket)
    {
        $HMSocketIDsOld[IPS_GetProperty($HMSocket, 'Host')] = $HMSocket;
        $Port = IPS_GetProperty($HMSocket, 'Port');
        if ($Port >= $nextPort)
            $nextPort = $Port + 1;
    }
    $HMSockets = array_merge($HMSockets, $HMSocketIDsOld); //IDs von Old zu Sockets mappen
}

foreach ($HMCcuAddress as $key => $IP)
{
    if ($HMSockets[$IP] < 10000)
    { //neu anlegen und port berechnen ?
        $xml = ReadCCUInterfaces($IP);
        if ($xml[0] === false)
            die($xml[1]);
        $OpenRF = (count($xml->xpath('//Interface[@Name="BidCos-RF"]')) > 0);
		$OpenHmIP = (count($xml->xpath('//Interface[@Name="HmIP-RF"]')) > 0);
		$OpenWired = (count($xml->xpath('//Interface[@Name="BidCos-Wired"]')) > 0);
        # "Erzeuge HomeMatic-Socket für CCU mit der Adresse " . $IP . PHP_EOL."  Und lege den Ereignis-Server in IPS auf Port " . $nextPort.PHP_EOL;
        echo "--------------------------------------------------------------------" . PHP_EOL;
        $ObjID = IPS_CreateInstance("{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}");
        usleep(50000 /* [Objekt #50000 existiert nicht] */);
        IPS_SetName($ObjID, "HomeMatic Socket - " . $IP);
		$Config = json_decode(IPS_GetConfiguration($ObjID),true);
		$Config['Host']= $IP;
		$Config['Open']= true;
		$Config['Port']= $nextPort;
		if (array_key_exists('Mode', $Config))
		{
			$Config['Mode']= $OpenWired ? 0 : 1;
			if ($OpenHmIP)
			echo "--------ACHTUNG HOMEMATIC IP INTERFACE GEFUNDEN ! IPS VERSION IST ABER ZU ALT ! -----".PHP_EOL; 
		} else {
			$Config['RFOpen'] = $OpenRF;
			$Config['WROpen'] = $OpenWired;
			$Config['IPOpen'] = $OpenHmIP;
		}
		IPS_SetConfiguration($ObjID,json_encode($Config));
        usleep(50000 /* [Objekt #50000 existiert nicht] */);
        @IPS_ApplyChanges($ObjID);
        $nextPort++;
        $HMSockets[$IP] = $ObjID;
    }
}

$HMDevices = IPS_GetInstanceListByModuleID("{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}");
$HMAdresses = array();

foreach ($HMDevices as $HMDevice) // vorhandene HM-Instanzen einlesen
{
    $HMAdresses[] = IPS_GetProperty($HMDevice, "Address");
}

foreach ($HMCcuAddress as $Key)
{
    $HMParent = $HMSockets[$Key];
    if (IPS_GetInstance($HMParent)['InstanceStatus'] <> 102)
    {
        if (IPS_GetInstance($HMParent)['InstanceStatus'] >= 200)
        {
            if (@IPS_ApplyChanges($HMSocket) === false)
                echo "Homematic-Socket (" . IPS_GetName($HMParent) . ") mit der InstanzID " . $HMParent . " konnte nicht reaktiviert werden." . PHP_EOL;
        }
        if (IPS_GetInstance($HMParent)['InstanceStatus'] <> 102)
        {
            echo "Homematic-Socket (" . IPS_GetName($HMParent) . ") mit der InstanzID " . $HMParent . " ist nicht aktiv." . PHP_EOL;
            echo "  Überspinge alle Geräte dieser CCU" . PHP_EOL;
            echo "--------------------------------------------------------------------" . PHP_EOL;
            continue;
        }
    }
//    $interfaces = ReadCCUInterfaces($IP);
    $xml = ReadCCUDevices($Key);
    if ($xml[0] === false)
        die($xml[1]);
    foreach ($xml->Room as $Room)
    {
        $Rooms[(string) $Room['Name']] = GetOrCreateCategoryByName($RaumCat, (string) $Room['Name']);
    }
    if ($OhneRaum)
        $Rooms['# Ohne Raum'] = GetOrCreateCategoryByName($RaumCat, '# Ohne Raum');
	if ($MitStatusmeldungen)
    	$Rooms['# Statusmeldungen'] = GetOrCreateCategoryByName($RaumCat, '# Statusmeldungen');
	if ($GewerkCat != -1)
	{
	    foreach ($xml->{'Function'} as $Function) // Gewerke erzeugen
	    {
	        if ($GewerkTyp == 0)
	            $Functions[(string) $Function['Name']] = GetOrCreateCategoryByName($GewerkCat, (string) $Function['Name']);
	        elseif ($GewerkTyp == 1)
	            $Functions[(string) $Function['Name']] = GetOrCreateDummyByName($GewerkCat, (string) $Function['Name']);
	    }
	}
    foreach ($xml->Device as $Device)
    {
        foreach ($Device->Channel as $Channel)
        {
            // Device nicht erzeugen, wenn...
            //schon vorhanden
            if (in_array((string) $Channel['Address'], $HMAdresses))
                continue;
            // kein Raum zugeordnet und ohne Raumzuordnung soll nicht erzeugt werden
            if (!isset($Channel->Room[0]['Name']) and ! $OhneRaum)
                continue;
            // Interface ermitteln
            switch ((string) $Device['Interface'])
            {
                case 'BidCos-RF':
                    $Protocol = 0;
                    break;
                case 'BidCos-Wired':
                    $Protocol = 1;
                    break;
				case 'HmIP-RF':
					$Protocol = 2;
					break;
                default:  // falsches Interface (nicht HmIP, Radio oder Wired)
//                    echo "Gerät mit der Addresse " . (string) $Channel['Address'] . " hat keine unterstütztes Interface (" . (string) $Device['Interface'] . ")." . PHP_EOL;
//                    echo "  Gerät mit Namen '" . (string) $Channel['Name'] . "' wird nicht erzeugt." . PHP_EOL;
//                    echo "--------------------------------------------------------------------" . PHP_EOL;
                    continue 2;
            }
            // Datenpunkte im XML zählen... wenn 0 nicht anlegen
            if (count($Channel->xpath('Point')) == 0)
                continue;
            // Typ vom Gerät aus der XML auswerten... für passendes Profil bestimmer DPs
            if (!array_key_exists((string) $Channel['ChnLabel'], $TypMappingProfil))
            {
                echo "Gerät mit der Addresse " . (string) $Channel['Address'] . " hat keinen bekannten Kanaltyp (" . (string) $Channel['ChnLabel'] . ")." . PHP_EOL;
                echo "  Gerät mit Namen '" . (string) $Channel['Name'] . "' wird nicht erzeugt." . PHP_EOL;
                echo "--------------------------------------------------------------------" . PHP_EOL;
                continue;
            }
            $Mapping = $TypMappingProfil[(string) $Channel['ChnLabel']];
            // Für Geräte ohne Raum den Pseudo-Raumnamen angeben.
            if (!isset($Channel->Room[0]['Name']))
            {
                $Room = $Channel->addChild('Room');
                if ((string) $Channel['ChnLabel'] == 'MAINTENANCE')
				{
					if (!$MitStatusmeldungen)
						continue;
                    $Room->addAttribute('Name', '# Statusmeldungen');
				}
                else
                    $Room->addAttribute('Name', '# Ohne Raum');
            }
            $HMDevice = GetOrCreateHMDevice($Rooms[(string) $Channel->Room[0]['Name']], (string) $Channel['Name'], (string) $Channel['Address'], $Protocol, $HMParent);
            // Jetzt zusätzliche Elemente Erzeugen
			if ($ScriptCat != -1)
			{
	            if (array_key_exists((string) $Channel['ChnLabel'], $AddOnMappings))
	            {
	                $AddOnMapping = $AddOnMappings[(string) $Channel['ChnLabel']];
	                foreach ($AddOnMapping as $ident => $Var)
	                {
	                    $VarId = IPS_CreateVariable($Var['VarTyp']);
	                    IPS_SetParent($VarId, $HMDevice);
	                    IPS_SetName($VarId, $ident);
	                    IPS_SetIdent($VarId, $ident);
	                }
	                $Mapping = array_merge($Mapping, $AddOnMapping);
	            }
			}
            if (array_key_exists('forceDP', $Mapping))
            {
                foreach ($Mapping['forceDP'] as $ident => $VarTyp)
                {
                    if (@IPS_GetObjectIDByIdent($ident, $HMDevice) === false)
                    {
                        $VarId = IPS_CreateVariable($VarTyp);
                        IPS_SetParent($VarId, $HMDevice);
                        IPS_SetName($VarId, $ident);
                        IPS_SetIdent($VarId, $ident);
                    }
                    @HM_RequestStatus($HMDevice, $ident);
                }
            }
            $Childs = IPS_GetChildrenIDs($HMDevice);
            if (count($Childs) == 0)
            {
                echo "Gerät mit der Addresse " . (string) $Channel['Address'] . " hat keine Datenpunkte." . PHP_EOL;
                echo "  Gerät mit Namen '" . (string) $Channel['Name'] . "' wird wieder gelöscht." . PHP_EOL;
                echo "--------------------------------------------------------------------" . PHP_EOL;
                IPS_DeleteInstance($HMDevice);
                continue;
            }
            $DeviceHidden = true;
            foreach ($Childs as $Var)
            {
                $Obj = IPS_GetObject($Var);
                if (array_key_exists($Obj['ObjectIdent'], $Mapping))
                {
				    if (array_key_exists('Name Raum',$Mapping[$Obj['ObjectIdent']]))
					{
	                    $Name = sprintf($Mapping[$Obj['ObjectIdent']]['Name Raum'], (string) $Channel['Name'], (string) $Channel->Room[0]['Name']);
	                    IPS_SetName($Var, $Name);
					}
				    if (array_key_exists('Profil',$Mapping[$Obj['ObjectIdent']]))
					{
	                    // Profil ändern, wenn nicht leer im Mapping
	                    if ($Mapping[$Obj['ObjectIdent']]['Profil'] <> '')
	                    {
	                        if (!@IPS_SetVariableCustomProfile($Var, $Mapping[$Obj['ObjectIdent']]['Profil']))
	                        {
	                            echo "Fehler bei Gerät mit der Addresse " . (string) $Channel['Address'] . " und Datenpunkt " . $Obj['ObjectIdent'] . PHP_EOL;
	                            echo "  Profil '" . $Mapping[$Obj['ObjectIdent']]['Profil'] . "' konnte nicht zugewiesen werden." . PHP_EOL;
	                            echo "--------------------------------------------------------------------" . PHP_EOL;
	                        }
	                    }
					}
					
				    if (array_key_exists('Action',$Mapping[$Obj['ObjectIdent']]))
					{
	                    if (IPS_GetVariable($Var)['VariableAction'] > 0)
	                    { // Standardaktion möglich
	                        if ($Mapping[$Obj['ObjectIdent']]['Action'] === true)
	                            IPS_SetVariableCustomAction($Var, 0);
	                        elseif ($Mapping[$Obj['ObjectIdent']]['Action'] === false)
	                            IPS_SetVariableCustomAction($Var, 1);
	                    } else
	                    {
	                        if ($Mapping[$Obj['ObjectIdent']]['Action'] === true)
	                        {
	                            echo "Gerät mit der Addresse " . (string) $Channel['Address'] . " hat keine Standardaktion," . PHP_EOL;
	                            echo "  für den Datenpunkt " . $Obj['ObjectIdent'] . " des Gerätes mit Namen '" . (string) $Channel['Name'] . "'." . PHP_EOL;
	                            echo "--------------------------------------------------------------------" . PHP_EOL;
	                        }
	                    }
	                    if (is_string($Mapping[$Obj['ObjectIdent']]['Action']) && ($ScriptCat != -1))
	                    {
	                        IPS_SetVariableCustomAction($Var, GetOrCreateScript($ScriptCat, $Mapping[$Obj['ObjectIdent']]['Action']));
	                    }
					}
                    $DeviceHidden = false;
					if ($GewerkCat != -1)
					{
						if (array_key_exists('Name Gewerk',$Mapping[$Obj['ObjectIdent']]))
						{
		                    // Link erzeugen zu Gewerk wenn Mapping nicht leer ist
		                    if ($Mapping[$Obj['ObjectIdent']]['Name Gewerk'] == '')
		                        continue;
		                    //
		                    // Schleife Gewerk
		                    foreach ($Channel->{'Function'} as $Function)
		                    {
		                        $Name = sprintf($Mapping[$Obj['ObjectIdent']]['Name Gewerk'], (string) $Channel['Name'], (string) $Channel->Room[0]['Name'], (string) $Function['Name']);
		                        $LnkID = IPS_CreateLink();
		                        IPS_SetLinkTargetID($LnkID, $Var);
		                        IPS_SetName($LnkID, $Name);
		                        IPS_SetParent($LnkID, $Functions[(string) $Function['Name']]);
		                    }
						}
					}
                }
                else
                {
                    IPS_SetHidden($Var, true);
                }
				
                if (in_array($Obj['ObjectIdent'], $Emulate))
                {
                    IPS_SetProperty($HMDevice, 'EmulateStatus', true);
                    usleep(50000 /* [Objekt #50000 existiert nicht] */);
                    IPS_ApplyChanges($HMDevice);
                }
                if (in_array($Obj['ObjectIdent'], $RequestState))
                    @HM_RequestStatus($HMDevice, $Obj['ObjectIdent']);
            }
            if ($DeviceHidden)
                IPS_SetHidden($HMDevice, true);
        }
        $HMNewAdresses[] = (string) $Channel['Address'];
    }

    /* if (!isset($HMNewAdresses))
      die("Keine neuen Geräte gefunden!");
      var_dump($HMNewAdresses); */
}

function ReadCCUInterfaces($ip)
{
    $Script = '
string index;                              ! Indexvariable
WriteLine("<xml>");
string SysVars=dom.GetObject(ID_INTERFACES).EnumUsedIDs();
foreach (index, SysVars)
{
  Write("<Interface ");
  object oitemID=dom.GetObject(index);
  Write("Name=\"" # oitemID.Name() # "\" ");
  Write("Address=\"" # oitemID.ID() # "\" ");
  WriteLine("/>");
}
WriteLine("</xml>");
';
    $rawdata = LoadHMScript($ip, $Script);
    if ($rawdata === false)
        return array(false, "Konnte HM-Daten nicht laden.");

    $pos = strrpos($rawdata, '<xml>');
    $data = utf8_encode(substr($rawdata, 0, $pos));
    $xml = @new SimpleXMLElement("<?xml version=\"1.0\"?>" . $data);
    if ($xml === false)
        return array(false, "XML malformed");
    return $xml;
}

function ReadCCUDevices($ip)
{
    $Script = 'string index;                              ! Indexvariable
string index2;
string index3;
WriteLine("<xml>");
string SysVars=dom.GetObject(ID_ROOMS).EnumUsedIDs();
foreach (index, SysVars)
{
  Write("<Room ");
  object oitemID=dom.GetObject(index);
  WriteLine("Name=\"" # oitemID.Name() # "\" />");
}
string SysVars=dom.GetObject(ID_FUNCTIONS).EnumUsedIDs();
foreach (index, SysVars)
{
  Write("<Function ");
  object oitemID=dom.GetObject(index);
  WriteLine("Name=\"" # oitemID.Name() # "\" />");
}
string SysVars=dom.GetObject(ID_DEVICES).EnumUsedIDs();
foreach (index, SysVars)
{
  Write("<Device ");
  object oitemID=dom.GetObject(index);
  string Name=oitemID.Name();
  Name = Name.Split("<");
  Name = Name.Split(">");
  Write("Name=\"" # Name # "\" ");
  Write("Interface=\"" # dom.GetObject(oitemID.Interface()));
  WriteLine("\">");
  foreach (index2, oitemID.Channels())
  {
   Write("<Channel ");
   object oitemID2=dom.GetObject(index2);
   string Name2=oitemID2.Name();
   Name2 = Name2.Split("<");
   Name2 = Name2.Split(">");
   Write("Name=\"" # Name2 # "\" ");
   Write("Address=\"" # oitemID2.Address() # "\" ");
   Write("ChnLabel=\"" # oitemID2.ChnLabel() # "\"");
   WriteLine(">");
   foreach (index3, oitemID2.ChnFunction())
   {
    Write("<Function ");
    object oitemID3=dom.GetObject(index3);
    string Name3=oitemID3.Name();
    Name3 = Name3.Split("<");
    Name3 = Name3.Split(">");
    WriteLine("Name=\"" # Name3 # "\" />");
   }
   foreach (index3, oitemID2.ChnRoom())
   {
    Write("<Room ");
    object oitemID3=dom.GetObject(index3);
    WriteLine("Name=\"" # oitemID3.Name() # "\" />");
   }
   foreach (index3, oitemID2.DPs())
   {
    Write("<Point ");
    object oitemID3=dom.GetObject(index3);
    WriteLine("Name=\"" # oitemID3.Name() # "\" />");
   }
  WriteLine("</Channel>");
  }
  WriteLine("</Device>");
}
WriteLine("</xml>");';
    $rawdata = LoadHMScript($ip, $Script);
    if ($rawdata === false)
        return array(false, "Konnte HM-Daten nicht laden.");

    $pos = strrpos($rawdata, '<xml>');
    $data = utf8_encode(substr($rawdata, 0, $pos));
//    var_dump($data);
//    die;
    $xml = @new SimpleXMLElement("<?xml version=\"1.0\"?>" . $data);
    if ($xml === false)
        return array(false, "XML malformed");
    return $xml;
}

function GetOrCreateHMDevice($Parent, $Name, $Address, $Protocol, $HMParent)
{
    $ObjID = IPS_CreateInstance("{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}");
    if (IPS_GetInstance($ObjID)['ConnectionID'] <> $HMParent)
    {
        IPS_DisconnectInstance($ObjID);
        IPS_ConnectInstance($ObjID, $HMParent);
    }
    IPS_SetParent($ObjID, $Parent);
    IPS_SetName($ObjID, $Name);
    IPS_SetProperty($ObjID, 'Address', $Address);
    IPS_SetProperty($ObjID, 'Protocol', $Protocol);
    IPS_SetProperty($ObjID, 'EmulateStatus', false);
    usleep(50000 /* [Objekt #50000 existiert nicht] */);
    @IPS_ApplyChanges($ObjID);
    /* 	 {
      echo "Error beim Erzeugen von Gerät ".$Address.PHP_EOL;
      //	 echo "  Gerät mit Namen ".$Name." wird wieder gelöscht.".PHP_EOL;
      //	 IPS_DeleteInstance($ObjID);
      //	 return false;
      } */
    return $ObjID;
}

function GetOrCreateCategoryByName($Parent, $Name)
{
    $ObjID = @IPS_GetObjectIDByName($Name, $Parent);
    if ($ObjID == 0)
    {
        $ObjID = IPS_CreateCategory();
        IPS_SetParent($ObjID, $Parent);
        IPS_SetName($ObjID, $Name);
    }
    return $ObjID;
}

function GetOrCreateDummyByName($Parent, $Name)
{
    $ObjID = @IPS_GetObjectIDByName($Name, $Parent);
    if ($ObjID == 0)
    {
        $ObjID = IPS_CreateInstance("{485D0419-BE97-4548-AA9C-C083EB82E61E}");
        IPS_SetParent($ObjID, $Parent);
        IPS_SetName($ObjID, $Name);
    }
    return $ObjID;
}

function LoadHMScript($HMAddress, $HMScript)
{
    $header[] = "Accept: text/plain,text/xml,application/xml,application/xhtml+xml,text/html";
    $header[] = "Cache-Control: max-age=0";
    $header[] = "Connection: close";
    $header[] = "Accept-Charset: UTF-8";
    $header[] = "Content-type: text/plain;charset=\"UTF-8\"";
    $ch = curl_init('http://' . $HMAddress . ':8181/ReadAll.exe');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $HMScript);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 20000);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 20000);
    $result = curl_exec($ch);
    curl_close($ch);
    if ($result === false)
    {
        return false;
    }
    return $result;
}

function GetOrCreateScript($parent, $ident)
{
    $ObjId = @IPS_GetObjectIDByIdent($ident, $parent);
    if ($ObjId === false)
    {
        $ObjId = IPS_CreateScript(0);
        IPS_SetParent($ObjId, $parent);
        IPS_SetIdent($ObjId, $ident);
        IPS_SetName($ObjId, $ident);
        IPS_SetScriptContent($ObjId, ScriptContent($ident));
    }
    return $ObjId;
}

function ScriptContent($ident)
{
    $data = array(
        'BLIND_SCRIPT' =>
        '<?
	$target = IPS_GetParent($_IPS[\'VARIABLE\']);
	switch ($_IPS[\'VALUE\'])
	{
		case -1:
			HM_WriteValueFloat($target,\'LEVEL\',0);
			break;
		case 0:
			HM_WriteValueBoolean($target,\'STOP\',true);
			break;
		case 1:
			HM_WriteValueFloat($target,\'LEVEL\',1);
			break;
	}
	?>',
        'CLIMATECONTROL_SCRIPT' =>
        '<?
	$target = IPS_GetParent($_IPS[\'VARIABLE\']);
	switch ($_IPS[\'VALUE\'])
	{
		case 0:
			HM_WriteValueBoolean($target,\'AUTO_MODE\',true);
			break;
		case 1:
			HM_WriteValueFloat($target,\'MANU_MODE\',GetValueFloat(IPS_GetObjectIDByIdent(\'SET_TEMPERATURE\',$target)));
			break;
		case 2:
			//\'PARTY_MODE_SUBMIT\' fehlt noch
			break;
		case 3:
			HM_WriteValueBoolean($target,\'BOOST_MODE\',true);
			break;
	}
	?>',
        'CONTROL_TEMP_SCRIPT' =>
        '<?
	$target = IPS_GetParent($_IPS[\'VARIABLE\']);
	switch ($_IPS[\'VALUE\'])
	{
		case 1:
			HM_WriteValueBoolean($target,\'LOWERING_MODE\',true);
			break;
		case 2:
			HM_WriteValueBoolean($target,\'COMFORT_MODE\',true);
			break;
	}
	?>',
        'DIMMER_SCRIPT' =>
        '<?
	$target = IPS_GetParent($_IPS[\'VARIABLE\']);
	switch (IPS_GetObject($_IPS[\'VARIABLE\'])[\'ObjectIdent\'])
	{
	case \'DIMMER_CONTROL\':
	switch ($_IPS[\'VALUE\'])
	{
		case -2:
			HM_WriteValueFloat($target,\'LEVEL\',0);
			break;
		case -1:
			HM_WriteValueFloat($target,\'RAMP_TIME\',GetValueFloat(IPS_GetObjectIDByIdent(\'RAMP_TIME\',$target)));
			HM_WriteValueFloat($target,\'LEVEL\',0);
			break;
		case 0:
			HM_WriteValueBoolean($target,\'RAMP_STOP\',true);
			HM_WriteValueBoolean($target,\'OLD_LEVEL\',true);
			break;
		case 1:
			HM_WriteValueBoolean($target,\'OLD_LEVEL\',true);
			break;
		case 2:
			HM_WriteValueFloat($target,\'RAMP_TIME\',GetValueFloat(IPS_GetObjectIDByIdent(\'RAMP_TIME\',$target)));
			HM_WriteValueFloat($target,\'LEVEL\',1);
			break;
		case 3:
			HM_WriteValueFloat($target,\'LEVEL\',1);
			break;
	}
	break;
	case \'RAMP_TIME\':
		SetValueFloat($_IPS[\'VARIABLE\'],$_IPS[\'VALUE\']);
	break;
	}
?>'
    );
    return $data[$ident];
}

?>
