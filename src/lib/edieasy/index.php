<?php
require_once("initialize.php");

$time_start = microtime_float();

$E = new EDIFACT();

// $E->DisplayClassEdifactInformation();

$E->LoadFile("EDIFACT0.txt");

$OK = $E->FindUNH(1);

$time_end = microtime_float();
$time = $time_end - $time_start;
echo "<BR><BR>FIN CHARGEMENT FILE EDI => TEMP DE REACTION $time secondes\n";

$MessageType = $E->GetMessageType();
echo "Message Type:".$MessageType."<BR>";
$MessageVersion =$E->GetMessageVersion();
echo "Message Version:".$MessageVersion."<BR>";
$MessageRelease = $E->GetMessageRelease();
echo "Message Release:".$MessageRelease."<BR>";
$MessageAgency = $E->GetMessageAgency();
echo "Message Agency:".$MessageAgency."<BR>";
$MessageAssociation = $E->GetMessageAssociation();
echo "Message Association:".$MessageAssociation."<BR>";

$E->LoadMessage($MessageType,$MessageVersion,$MessageRelease);
$time_end = microtime_float();
$time = $time_end - $time_start;
echo "<BR><BR>FIN CHARGEMENT STRUCTURE MESSAGE => TEMP DE REACTION $time secondes\n";

echo "<BR>**** VALIDATION MESSAGE *** <BR>";
// $E->ValidMessage();
echo "<BR>**** FIN VALIDATION MESSAGE *** <BR>";
// $E->DisplayIndexationMessage();
$time_end = microtime_float();
$time = $time_end - $time_start;
echo "<BR><BR>FIN VALIDATION MESSAGE => TEMP DE REACTION $time secondes\n";
echo "<BR>";

while ($E->ReadDirectMessage()) {
	// Recherche NAD+IV dans le groupe "2"
	// $E->DisplayCurrentSegment();
	// echo "IsCurrentGroupName : ".$E->GetCurrentGroupName()."<BR>";
	if ($E->IsCurrentGroupSegment("2","NAD")) {
		// echo "IsCurrentSegmentName:".$E->GetCurrentSegmentName()."<BR>";
				if (strcmp($E->GetCurrentDataSegment("10.3035"),"IV") === 0) {
					$NAD_IV = $E->GetCurrentDataSegment("20.C082.10.3039");
					echo "IDENTIFIANT FACTURE A (NAD+IV):".$NAD_IV."<BR>";
					$E->PutBUFFER("FACTURE",1,1,"INVOICE");
					$E->PutBUFFER("FACTURE",1,10,$NAD_IV);
					$E->PutBUFFER("FACTURE",2,12,str_pad($NAD_IV,35,"#"));
					$E->PutBUFFER("FACTURE",2,1,"INV_LINE");
					$E->PutBUFFER("FACTURE",2,100,"POS100");
				} else {
					echo "ERREUR...NAD NON TRAITE ICI:".$E->GetCurrentDataSegment("10.3035")."<BR>";
					$MessageVersion =$E->GetDataSegment("PCI.10.4233");
					echo "Message Version:".$MessageVersion."<BR>";
					// $E->ValidCurrentSegment();
					// $E->DisplayCurrentSegment();
				}
	}
	
}
$time_end = microtime_float();
$time = $time_end - $time_start;
echo "<BR><BR>FIN TRAITEMENT FILE EDI => TEMP DE REACTION $time secondes\n";

echo "<BR><BR>SORTIE DE LE STRUCTURE : FACTURE<BR>";
echo "--------------------------------<BR>";
$E->PrintOutputFile("FACTURE");

$time_end = microtime_float();
$time = $time_end - $time_start;
echo "<BR><BR>TEMP DE TRAITEMENT GLOBAL $time secondes\n";

?>