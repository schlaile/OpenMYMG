<?php

if (!isset($_EDIFACT_)) {

	class EDIFACT {
		
		var $CLASS_EDIFACT = array("VERSION" => "0.15", 
					   "DATE_UPDATE" => "16/11/2010",
					   "DESCRIPTION" => "1.UNIQUEMENT Réception message EDIFACT<BR>
					  	             2.Validation Interchange multi-Message<BR>
						             3.Gestion UNA<BR>
						             4.Read Message Indexé (déjà validé) ou Fichier EDIFACT Direct (validation à la lecture)");
		
		var $UNA_INIT = "UNA:+.? '";
		var $UNA       = "";

		var $COUNT_SEGMENT=0;
		var $COUNT_MESSAGE=0;
		var $COUNT_END_MESSAGE=0;
		var $Cpt_MESSAGE=0;
		
		var $UNA_Presence = FALSE;
		var $UNB_Presence = FALSE;

		var $TABLE_MESSAGE="";
		var $TABLE_SEGMENT="";
		var $TABLE_SEGMENT_ELEMENT="";
		var $TABLE_COMPOSITE="";
		var $TABLE_COMPOSITE_ELEMENT="";
		var $TABLE_ELEMENT="";

		var $FILENAME = "";
		var $FileEDIFACT = "";
		var $VALEURSEGMENT = "";
		var $TEMP = "";
		
		var $TABLE_INTERCHANGE = "";
		var $PointeurMessage = 0;
		
		function AddCountSegment() {
			$this->COUNT_SEGMENT++;
		}
		function GetCountSegment() {
			return $this->COUNT_SEGMENT++;
		}
		function ResetCountSegment() {
			$this->COUNT_SEGMENT = 0;
		}


		function AddCountMessage() {
			$this->COUNT_MESSAGE++;
		}
		function AddCountEndMessage() {
			$this->COUNT_END_MESSAGE++;
		}
		function GetCountMessage() {
			return $this->COUNT_MESSAGE;
		}

		function AddElement($Element,$Type,$Min,$Max,$Description) {
			$this->TABLE_ELEMENT[$Element] = array("TYPE"=>$Type,"MINI"=>$Min,"MAXI"=>$Max,"DESCRIPTION"=>$Description);
		}

		function AddComposite($Composite,$Description) {
			$this->TABLE_COMPOSITE[$Composite] = array("DESCRIPTION"=>$Description);
		}
		function AddElement2Composite($Composite,$Rang,$Element,$Mandatory) {
			$this->TABLE_COMPOSITE_ELEMENT[$Composite][$Rang] = array("ELEMENT"=>$Element,"MANDATORY"=>$Mandatory);
		}
		
		function AddSegment($Segment,$Description) {
			$this->TABLE_SEGMENT[$Segment] = array("DESCRIPTION"=>$Description);
		}
		function AddElement2Segment($Segment,$Rang,$Element,$Type) {
			$this->TABLE_SEGMENT_ELEMENT[$Segment][$Rang] = array("ELEMENT"=>$Element,"MANDATORY"=>$Type);
		}

		function LoadFile($EDIFile) {
			$this->FileEDIFACT = $EDIFile;
			$this->FILENAME = fopen($EDIFile,"r");
			
			if ($this->TEMP = fgets($this->FILENAME,10)) {

				$UNA_Presence = $this->LoadUNA();
				if (strcmp($UNA_Presence,"1") === 0) {
				   $this->TEMP = fread($this->FILENAME,4096);
				} else {
					$this->SetUNA($this->UNA_INIT);
				}
				// Save UNA Value on the Table Information Interchange
				$this->TABLE_INTERCHANGE["INT_UNA"]=$this->GetUNA();
				
				$this->LoadStartUNB();
				$UNB_Presence = $this->LoadUNB();
				if ($UNB_Presence === FALSE)  {
					$this->TABLE_INTERCHANGE["INT_ERROR"]="UNB NOT PRESENT...<BR>";
					fclose($this->FILENAME);
					return FALSE;
				} else { 
					//Save UNB value on the Table Information Interchange
					$this->TABLE_INTERCHANGE["INT_UNB"]=$this->VALEURSEGMENT;
					// echo "VALEURSEGMENT:".$this->VALEURSEGMENT."<BR>";
					// Retreive UNB version before load UNB Version
					$DataVersionUNB = $this->GetDataSegment("UNB.10.S001.20.0002");
					$this->TABLE_INTERCHANGE["INT_UNB_VERSION"]=$DataVersionUNB;
					$this->LoadSegmentServices($DataVersionUNB,"UNB");
					
					$Test = $this->ValidSegment($this->VALEURSEGMENT);
					if ($Test === FALSE) {
						$this->DisplaySegment($this->VALEURSEGMENT);
						return FALSE;
					} 
					$DataVersionUNO = $this->GetDataSegment("UNB.10.S001.10.0001");
					$this->TABLE_INTERCHANGE["INT_UNB_CHARSET"]=$DataVersionUNO;
					$this->LoadSegmentServices($DataVersionUNB,"UNZ");
                                        $LECTURE = TRUE;
					while ($LECTURE) {				
						$Data = $this->ExtractSegment();
						if (strcmp($Data,"_EOF_") === 0) {
							// "_EOF_" => End file ...
							$LECTURE = FALSE; 
						} else {
							$this->VALEURSEGMENT = $Data;
							$Name = $this->NameSegment($Data);
							if (strcmp($Name,"UNH") === 0) {
								// Add 1 to Count Message variable , Add 1 to Count Segment
								$this->AddCountMessage();
								$this->AddCountSegment();
								// Save UNH value on table Interchage
								$this->TABLE_INTERCHANGE["MES_UNH_".$this->GetCountMessage()]=$Data;							
							} else {
								if (strcmp($Name,"UNT") === 0) {
									// Add 1 to Count END message
									$this->AddCountEndMessage();
									if ($this->COUNT_END_MESSAGE != $this->COUNT_MESSAGE) {
										// Error UNH and UNT not equal...
										$this->TABLE_INTERCHANGE["MES_ERROR"]="ERROR : COUNT UNT(".$this->COUNT_END_MESSAGE.") NOT EQUAL UNH (".$this->COUNT_MESSAGE.")<BR>";
										$LECTURE=FALSE;
									} else {
										// Add 1 to Count Segment, Memorize data on Tbale Interchange Info, Reset Count Segment
										$this->AddCountSegment();
										$this->TABLE_INTERCHANGE["MES_UNT_".$this->GetCountMessage()]=$Data;
										$this->TABLE_INTERCHANGE["MES_COUNT_SEG_".$this->GetCountMessage()]=$this->GetCountSegment();
										$this->ResetCountSegment();
									}
								} else {
									$this->AddCountSegment();
								}
							}
						}
					}
					
					$Name = $this->NameSegment($Data);
					if (strcmp($Name,"UNZ") === 1) {
						// File not finish by UNZ ???
						$this->TABLE_INTERCHANGE["INT_ERROR"]="UNZ ISN'T THE LAST SEGMENT...";
						fclose($this->FILENAME);
						return FALSE;
					} else {
						// Save Total Messages on the Interchange
						$this->TABLE_INTERCHANGE["MES_COUNT"]=$this->COUNT_MESSAGE;	
					}
					return TRUE;
				}
				fclose($this->FILENAME);
			} else {
				fclose($this->FILENAME);
				return FALSE;
			}
		}

		// Load UNA from the file
		function LoadUNA() {
			$TestUNA = substr($this->TEMP,0,3);
			if (strcmp($TestUNA,"UNA") === 0) {
				$this->UNA["COMPONENT"]  = $this->TEMP[3];
				$this->UNA["ELEMENT"]    = $this->TEMP[4];
				$this->UNA["DECIMAL"]    = $this->TEMP[5];
				$this->UNA["SUSPENSIF"]  = $this->TEMP[6];
				$this->UNA["SPACE"]      = $this->TEMP[7];
				$this->UNA["ENDSEGMENT"] = $this->TEMP[8];
				return TRUE;
			}
			return FALSE;
		}

		// Load UNA from Field "UNA:+.? '"
		function SetUNA($DataUNA) {
			$TestUNA = substr($DataUNA,0,3);
			if (strcmp($TestUNA,"UNA") === 0) {
				$this->UNA["COMPONENT"]  = $DataUNA[3];
				$this->UNA["ELEMENT"]    = $DataUNA[4];
				$this->UNA["DECIMAL"]    = $DataUNA[5];
				$this->UNA["SUSPENSIF"]  = $DataUNA[6];
				$this->UNA["SPACE"]      = $DataUNA[7];
				$this->UNA["ENDSEGMENT"] = $DataUNA[8];
				return TRUE;
			}
			return FALSE;
		}
		
		// Get UNA from Field "UNA:+.? '"
		function GetUNA() {
			return "UNA".$this->UNA["COMPONENT"].$this->UNA["ELEMENT"].$this->UNA["DECIMAL"].$this->UNA["SUSPENSIF"].$this->UNA["SPACE"].$this->UNA["ENDSEGMENT"];
		}
		
		// Retreive Nam segment from global varianle VALEURSEGMENT
		function NameSegment($DataIn) {
			$Valeur = "";
			$indice = 0;
			$maxlu  = strlen($DataIn)-1;
			$endread = FALSE;
			if ($maxlu < 0)
				$endread = TRUE;
			while ($endread === FALSE) {
				if ($indice > $maxlu) {
					$endread = TRUE;
					$Valeur = "";
				}
				if ($endread === FALSE)  {
					$Car = $DataIn[$indice];
					$indice++;
					if (strcmp($Car,$this->UNA["ELEMENT"]) === 0) {
						$endread = TRUE;
					} else {
						$Valeur .= $Car;
					}
				}
			}
			return $Valeur;
		}

		// Load UNB minimum syntaxe to get UNB version
		function LoadStartUNB() {
			$FileUNB = fopen("./EDIFACT/SERVICES/UNB.seg","r");	
			$Line = 0;
			while ($TEMP = fgets($FileUNB)) {
				$Line ++;
				if ($Line === 1) {
					$SEG         = substr($TEMP,0,3);
					$Description = substr($TEMP,7,50);
					// echo "SGEMENT:".$SEG." - DESCRIPTION :".$Description."<BR>";
					$this->AddSegment($SEG,$Description);
				} else {
					$RANG        = trim(substr($TEMP,0,3));
					if ($RANG <> "") {
						$LineSub = 0;
						if ($RANG[0] === "0") {
							$RANG = substr($RANG,1,2);
						}
						$Element     = substr($TEMP,6,4);
						$Description = substr($TEMP,12,43);
						$Mandatory   = substr($TEMP,56,1);
						$Type = trim(strtoupper(substr($TEMP,62,2)));
						
						// echo "Segment:".$SEG." - Rang:".$RANG." - Element:".$Element." - Mandatory:".$Mandatory."<BR>";
						$this->AddElement2Segment($SEG,$RANG,$Element,$Mandatory);
						
						if ($Type <> "") {
							$LongMini = 0;
							$LongMaxi = 0;
							if ($Type === "AN") {
								$PointPoint = substr($TEMP,64,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,66,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,64,2);
									$LongMaxi = $LongMini;
								}
							} else {
								$Type = strtoupper(substr($TEMP,62,1));
								$LongMaxi = substr($TEMP,63,2);
								$LongMini = $LongMaxi;
							}
							// echo "Sub-Element:".$Element." - Type:".$Type." - Mini:".$LongMini." - Maxi:".$LongMaxi."- DESCRIPTION :".$Description."<BR>";
							$this->AddElement($Element,$Type,$LongMini,$LongMaxi,$Description); 
						} else {
							
							// echo "Element:".$Element." - DESCRIPTION :".$Description."<BR>";
							$this->AddComposite($Element,$Description);
						}
					} else {
						$SubElement = trim(substr($TEMP,6,4));
						if ($SubElement <> "") {
							$LineSub++;
							$LineSub10 = $LineSub * 10;
							$Description = substr($TEMP,13,42);
							$Mandatory   = substr($TEMP,56,1);
							$Type = strtoupper(substr($TEMP,62,2));
							$LongMini = 0;
							$LongMaxi = 0;
							if ($Type === "AN") {
								$PointPoint = substr($TEMP,64,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,66,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,64,2);
									$LongMaxi = $LongMini;
								}
							} else {
								$Type = strtoupper(substr($TEMP,62,1));
								$LongMaxi = substr($TEMP,63,2);
								$LongMini = $LongMaxi;
							}
							// echo "Element:".$Element." - Rang:".$LineSub10." - Sub-Element:".$SubElement." - Mandatory:".$Mandatory."<BR>";
							$this->AddElement2Composite($Element,$LineSub10,$SubElement,$Mandatory);
							// echo "Sub-Element:".$SubElement." - Type:".$Type." - Mini:".$LongMini." - Maxi:".$LongMaxi."- DESCRIPTION :".$Description."<BR>";
							$this->AddElement($SubElement,$Type,$LongMini,$LongMaxi,$Description); 
						}
					}
				}
			}
 			fclose($FileUNB);
		}
		
		// Load Segment Services dasn la version N 
		function LoadSegmentServices($Version,$Segment) {
			// echo "Version:".$Version."<BR>";
			$FileServices = fopen("./EDIFACT/SERVICES/".$Version."/".$Segment.".seg","r");	
			$Line = 0;
			while ($TEMP = fgets($FileServices)) {
				$Line ++;
				if ($Line === 1) {
					$SEG         = substr($TEMP,0,3);
					$Description = substr($TEMP,7,50);
					// echo "SGEMENT:".$SEG." - DESCRIPTION :".$Description."<BR>";
					$this->AddSegment($SEG,$Description);
				} else {
					$RANG        = trim(substr($TEMP,0,3));
					if ($RANG <> "") {
						$LineSub = 0;
						if ($RANG[0] === "0") {
							$RANG = substr($RANG,1,2);
						}
						$Element     = substr($TEMP,6,4);
						$Description = substr($TEMP,12,43);
						$Mandatory   = substr($TEMP,56,1);
						$Type = trim(strtoupper(substr($TEMP,62,2)));
						
						// echo "Segment:".$SEG." - Rang:".$RANG." - Element:".$Element." - Mandatory:".$Mandatory."<BR>";
						$this->AddElement2Segment($SEG,$RANG,$Element,$Mandatory);
						
						if ($Type <> "") {
							$LongMini = 0;
							$LongMaxi = 0;
							if ($Type === "AN") {
								$PointPoint = substr($TEMP,64,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,66,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,64,2);
									$LongMaxi = $LongMini;
								}
							} else {
								$Type = strtoupper(substr($TEMP,62,1));
								$PointPoint = substr($TEMP,63,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,65,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,63,2);
									$LongMaxi = $LongMini;
								}
							}
							// echo "Sub-Element:".$Element." - Type:".$Type." - Mini:".$LongMini." - Maxi:".$LongMaxi."- DESCRIPTION :".$Description."<BR>";
							$this->AddElement($Element,$Type,$LongMini,$LongMaxi,$Description); 
						} else {
							
							// echo "Element:".$Element." - DESCRIPTION :".$Description."<BR>";
							$this->AddComposite($Element,$Description);
						}
					} else {
						$SubElement = trim(substr($TEMP,6,4));
						if ($SubElement <> "") {
							$LineSub++;
							$LineSub10 = $LineSub * 10;
							$Description = substr($TEMP,13,42);
							$Mandatory   = substr($TEMP,56,1);
							$Type = strtoupper(substr($TEMP,62,2));
							$LongMini = 0;
							$LongMaxi = 0;
							if ($Type === "AN") {
								$PointPoint = substr($TEMP,64,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,66,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,64,2);
									$LongMaxi = $LongMini;
								}
								
							} else {
								$Type = strtoupper(substr($TEMP,62,1));
								$PointPoint = substr($TEMP,63,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,65,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,63,2);
									$LongMaxi = $LongMini;
								}
							}
							// echo "Element:".$Element." - Rang:".$LineSub10." - Sub-Element:".$SubElement." - Mandatory:".$Mandatory."<BR>";
							$this->AddElement2Composite($Element,$LineSub10,$SubElement,$Mandatory);
							// echo "Sub-Element:".$SubElement." - Type:".$Type." - Mini:".$LongMini." - Maxi:".$LongMaxi."- DESCRIPTION :".$Description."<BR>";
							$this->AddElement($SubElement,$Type,$LongMini,$LongMaxi,$Description); 
						}
					}
				}
			}
			$this->TABLE_SEGMENT[$Segment]["VALEUR"]="";
 			fclose($FileServices);	
		}
		
		// Load UNB from File
		function LoadUNB() {
			$this->VALEURSEGMENT = $this->ExtractSegment();
			$NameUNB = $this->NameSegment($this->VALEURSEGMENT);
			if (strcmp($NameUNB,"UNB") === 0) {
				// if UNB save on TABLE SEGMENT
				$this->TABLE_SEGMENT[$NameUNB]["VALEUR"]=$this->VALEURSEGMENT;
				return TRUE;
			}
			return FALSE;
		}
		
		// Get Data on the TABLE_SEGMENT;  DataList example : UNB.10.S001.20.0002
		function GetDataSegment($DataList) {
			$Valeur = " ";
			$donnees = explode(".", $DataList);
			$Element = $donnees[2];
			// echo "GetDataSegment:".$DataList."<BR>";
			$key = $this->TABLE_SEGMENT_ELEMENT[$donnees[0]][$donnees[1]]["ELEMENT"];
			if (strcmp($key,$Element) === 0) {
				$Valeur0 = $this->ExtractElement($this->VALEURSEGMENT,$donnees[1]);        // $this->TABLE_SEGMENT[$donnees[0]]["VALEUR"],$donnees[1]);
				// echo "Valeur0:".$Valeur0." - ".$this->VALEURSEGMENT."- ".$donnees[1]."<BR>";
				$this->TABLE_SEGMENT[$donnees[0]][$donnees[1].".".$Element] = $Valeur0;
				if (isset($donnees[4])) {
					$SubElement = $donnees[4];
					$SubElement = substr($SubElement,-4);
					$key = $this->TABLE_COMPOSITE_ELEMENT[$Element][$donnees[3]]["ELEMENT"];
					if (strcmp($key,$SubElement) === 0) {
						$Valeur0 = $this->ExtractSubElement($this->TABLE_SEGMENT[$donnees[0]][$donnees[1].".".$Element],$donnees[3]);
						$this->TABLE_SEGMENT[$donnees[0]][$donnees[1].".".$Element.".".$donnees[3].".".$SubElement] = $Valeur0;
						return $Valeur0;
					} else {
						return "";
					}
				} else {
					return $Valeur0;
				}
			} else {
			   return "";
			}
			return $Valeur;
		}
		
		// Valid la structure d'un segment
		function ValidSegment($DataIn) {
			$Name = $this->NameSegment($DataIn);
			$this->TABLE_SEGMENT[$Name]["VALEUR"] = $DataIn;
			$CountElement = $this->GetNumberElement($DataIn);
			$GlobalErreur = FALSE;
			$Seg = @$this->TABLE_SEGMENT[$Name]["DESCRIPTION"];
			if (strcmp($Seg,"") === 0) 
				return FALSE;
			foreach ($this->TABLE_SEGMENT_ELEMENT[$Name] as $RangElement => $Value) {
				$MANDATORY  = $Value["MANDATORY"];
				$TU = $Value["ELEMENT"][0];
				if (($TU >= "0") && ($TU <= "9")) {
					$Description = $this->TABLE_ELEMENT[$Value["ELEMENT"]]["DESCRIPTION"];
					$TypeElement = strtolower($this->TABLE_ELEMENT[$Value["ELEMENT"]]["TYPE"]);
					$Mini = $this->TABLE_ELEMENT[$Value["ELEMENT"]]["MINI"];
					$Maxi = $this->TABLE_ELEMENT[$Value["ELEMENT"]]["MAXI"];
					$TypeElement .= $Maxi;
					$TU = TRUE;
				} else {
					$Description = $this->TABLE_COMPOSITE[$Value["ELEMENT"]]["DESCRIPTION"];
					$TU = FALSE;
				}
				$DataList = $Name.".".$RangElement.".".$Value["ELEMENT"];
				$ValueElement = $this->GetDataSegment($DataList);
				$this->TABLE_SEGMENT[$DataList] = $ValueElement;
				$CountSubElement = $this->GetNumberSubElement($ValueElement);
				$LongElement = strlen($ValueElement);
				if ($MANDATORY === "M") {
					if ($ValueElement <> "") {
						if (($TU === TRUE) && ($ValueElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
							$GlobalErreur = TRUE;
							$GlobalEltErreur = TRUE;
							$GlobalErreurMessage = "Length of Element :".$Value["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
						} 
					} else {
						$GlobalEltErreur = TRUE;
						$GlobalErreurMessage = "This element is mandatory :".$Value["ELEMENT"];
						$GlobalErreur = TRUE;
					}
				} else {
					if (($TU === TRUE) && ($ValueElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
						$GlobalErreur = TRUE;
						$GlobalEltErreur = TRUE;
						$GlobalErreurMessage = "Length of Element :".$Value["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
					} 
				}
				if ($TU === FALSE) {
					foreach ($this->TABLE_COMPOSITE_ELEMENT[$Value["ELEMENT"]] as $RangSubElement => $ValueSubE) {
						$MANDATORY  = $ValueSubE["MANDATORY"];
						$Description = $this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["DESCRIPTION"];
						$TypeElement = strtolower($this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["TYPE"]);
						$Mini = $this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["MINI"];
						$Maxi = $this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["MAXI"];
						$DataListE = $DataList.".".$RangSubElement.".".$ValueSubE["ELEMENT"];
						$this->TABLE_SEGMENT[$DataListE] = $this->GetDataSegment($DataList);
						$ValueSubElement = $this->GetDataSegment($DataListE);
						$LongElement = strlen($ValueSubElement);
						if ($MANDATORY === "M") {
							if ($ValueSubElement <> "") {
								if (($ValueSubElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
									$GlobalErreur = TRUE;
									$GlobalSubErreur = TRUE;
									$GlobalErreurMessage = "Length of Element :".$ValueSubE["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
								}
							} else {
								if ($ValueElement <> "") {
									$GlobalSubErreur = TRUE;
									$GlobalErreurMessage = "This element is mandatory :".$ValueSubE["ELEMENT"];
									$GlobalErreur = TRUE;
								} 
							}
						} else {
							if (($ValueSubElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
								$GlobalErreur = TRUE;
								$GlobalSubErreur = TRUE;
								$GlobalErreurMessage = "Length of Element :".$ValueSubE["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
							} 
						}
					}
					$MaxRangSubElement = $RangSubElement / 10;
					if ($CountSubElement > $MaxRangSubElement) {
						$GlobalErreurMessage = "DATA : ".$ValueElement."<BR>COUNT SUB-ELEMENT in data : ".$CountSubElement."<BR> Element define in directory :".$MaxRangSubElement;
					}
				}
			}
			$MaxRangElement = $RangElement / 10;
			if ($CountElement > $MaxRangElement) {						
				$GlobalErreurMessage = "DATA : ".$this->TABLE_SEGMENT[$Name]["VALEUR"]."<BR>ELEMENT COUNT:".$CountElement."<BR> Element define in directory :".$MaxRangElement;
			}
			if ($GlobalErreur === TRUE) {
				$this->TABLE_INTERCHANGE["ERROR_TEXT"]=$GlobalErreurMessage;
				return FALSE;
			}
			return TRUE;
		}
		
		// Number of Element in the segment
		function GetNumberElement($DataIn) {
			$Data = str_replace($this->UNA["SUSPENSIF"].$this->UNA["ELEMENT"],"",$DataIn);
			$Table_Element = explode($this->UNA["ELEMENT"],$Data);
			$NumberElement = count($Table_Element) - 1;
			return $NumberElement;
		}
		
		// Number of Element in the segment
		function GetNumberSubElement($DataIn) {
			$Data = str_replace($this->UNA["SUSPENSIF"].$this->UNA["COMPONENT"],"",$DataIn);
			$Table_SubElement = explode($this->UNA["COMPONENT"],$Data);
			$NumberSubElement = count($Table_SubElement);
			return $NumberSubElement;
		}
		
		// Display detail segment
		function DisplaySegment($DataIn) {
			$Name = $this->NameSegment($DataIn);
			$Seg = @$this->TABLE_SEGMENT[$Name]["DESCRIPTION"];
			echo "<table cellspacing='1' cellpadding='2' border='1'>
			<caption><b>".$Name."&nbsp;=&nbsp;".$Seg."</b></caption>
			<thead>
				<tr>
					<th  bgcolor='green'>RANG</th>
					<th  bgcolor='#ECECEC'>ELEMENT</th>
					<th>Sub-ELEMENT</th>
					<th bgcolor='#CECECE'>DESCRIPTION</th>
					<th>TYPE</th>
					<th bgcolor='red'>MANDATORY</th>
					<th bgcolor='#FFEEFF'>VALUE</th>
					<th bgcolor='#AABBCC'>CHECK</th>
				</tr>
			</thead>
				<tbody>";
				if (strcmp($Seg,"") === 0) {
					$CountElement = 0;
					$GlobalErreur = TRUE;
				} else {
				$CountElement = $this->GetNumberElement($this->TABLE_SEGMENT[$Name]["VALEUR"]);
				$GlobalErreur = FALSE;
				foreach ($this->TABLE_SEGMENT_ELEMENT[$Name] as $RangElement => $Value) {
					echo "<tr>";
					$MANDATORY  = $Value["MANDATORY"];
					$GlobalEltErreur = FALSE;
					$GlobalEltErreurMessage = "";
					echo "<td  bgcolor='green'>".$RangElement."</td>";
					echo "<td  bgcolor='#ECECEC'>".$Value["ELEMENT"]."</td>";
					echo "<td>&nbsp</td>";
					$TU = $Value["ELEMENT"][0];
					if (($TU >= "0") && ($TU <= "9")) {
						$Description = $this->TABLE_ELEMENT[$Value["ELEMENT"]]["DESCRIPTION"];
						$TypeElement = strtolower($this->TABLE_ELEMENT[$Value["ELEMENT"]]["TYPE"]);
						$Mini = $this->TABLE_ELEMENT[$Value["ELEMENT"]]["MINI"];
						$Maxi = $this->TABLE_ELEMENT[$Value["ELEMENT"]]["MAXI"];
						if ($Mini <> $Maxi) {
							if ($Mini > 0) {
								$TypeElement .= $Mini;
							}
							if ($Maxi > 1) {
								$TypeElement .= "..";
							}
						}
						$TypeElement .= $Maxi;
						$TU = TRUE;
					} else {
						$Description = $this->TABLE_COMPOSITE[$Value["ELEMENT"]]["DESCRIPTION"];
						$TypeElement = "&nbsp;";
						$TU = FALSE;
					}
					echo "<td bgcolor='#CECECE'>".$Description."</td>";
					echo "<td>".$TypeElement."</td>";
					echo "<td>".$Value["MANDATORY"]."</td>";
					$DataList = $Name.".".$RangElement.".".$Value["ELEMENT"];
					$ValueElement = $this->GetDataSegment($DataList);
					$this->TABLE_SEGMENT[$DataList] = $ValueElement;
					$CountSubElement = $this->GetNumberSubElement($ValueElement);
					if ($TU === TRUE) {
						if ($ValueElement === "") {
							echo "<td>&nbsp;</td>";
						} else {
							echo "<td>".$ValueElement."</td>";
						}
					} else {
						echo "<td>&nbsp</td>";
					}
					$LongElement = strlen($ValueElement);
					if ($MANDATORY === "M") {
						if ($ValueElement <> "") {
							if (($TU === TRUE) && ($ValueElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
								$GlobalErreur = TRUE;
								$GlobalEltErreur = TRUE;
								$GlobalEltErreurMessage = "Length of Element :".$Value["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
								echo "<td bgcolor='red'>X</td>";
							} else {
								echo "<td bgcolor='green'>X</td>";
							}
						} else {
							echo "<td bgcolor='red'>X</td>";
							$GlobalEltErreur = TRUE;
							$GlobalEltErreurMessage = "This element is mandatory :".$Value["ELEMENT"];
							$GlobalErreur = TRUE;
						}
					} else {
						if (($TU === TRUE) && ($ValueElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
							echo "<td bgcolor='red'>X</td>";
							$GlobalErreur = TRUE;
							$GlobalEltErreur = TRUE;
							$GlobalEltErreurMessage = "Length of Element :".$Value["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
						} else {
							echo "<td bgcolor='green'>X</td>";
						}
					}
					echo "</tr>";
					if ($GlobalEltErreur === TRUE) {
						echo "<tr><td bgcolor='red' colspan='8'>".$GlobalEltErreurMessage."<BR></td></tr>";							
					}
					if ($TU === FALSE) {
						foreach ($this->TABLE_COMPOSITE_ELEMENT[$Value["ELEMENT"]] as $RangSubElement => $ValueSubE) {
							$MANDATORY  = $ValueSubE["MANDATORY"];
							$GlobalSubErreur = FALSE;
							$GlobalSubErreurMessage = "";
							echo "<tr>";
							echo "<td>&nbsp</td>";
							echo "<td  bgcolor='green'>".$RangSubElement."</td>";
							echo "<td  bgcolor='#ECECEC'>".$ValueSubE["ELEMENT"]."</td>";
							$Description = $this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["DESCRIPTION"];
							$TypeElement = strtolower($this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["TYPE"]);
							$Mini = $this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["MINI"];
							$Maxi = $this->TABLE_ELEMENT[$ValueSubE["ELEMENT"]]["MAXI"];
							if ($Mini <> $Maxi) {
								if ($Mini > 0) {
									$TypeElement .= $Mini;
								}
								if ($Maxi > 1) {
									$TypeElement .= "..";
								}
							}
							$TypeElement .= $Maxi;
							echo "<td bgcolor='#CECECE'>".$Description."</td>";
							echo "<td>".$TypeElement."</td>";
							echo "<td>".$ValueSubE["MANDATORY"]."</td>";
							$DataListE = $DataList.".".$RangSubElement.".".$ValueSubE["ELEMENT"];
							$this->TABLE_SEGMENT[$DataListE] = $this->GetDataSegment($DataList);
							$ValueSubElement = $this->GetDataSegment($DataListE);
							if ($ValueSubElement === "") {
								echo "<td>&nbsp;</td>";
							} else {
								echo "<td>".$ValueSubElement."</td>";
							}
							$LongElement = strlen($ValueSubElement);
							if ($MANDATORY === "M") {
								if ($ValueSubElement <> "") {
									if (($ValueSubElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
										$GlobalErreur = TRUE;
										$GlobalSubErreur = TRUE;
										$GlobalSubErreurMessage = "Length of Element :".$ValueSubE["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
										echo "<td bgcolor='red'>X</td>";
									} else {
										echo "<td bgcolor='green'>X</td>";
									}
								} else {
									if ($ValueElement <> "") {
										echo "<td bgcolor='red'>X</td>";
										$GlobalSubErreur = TRUE;
										$GlobalSubErreurMessage = "This element is mandatory :".$ValueSubE["ELEMENT"];
										$GlobalErreur = TRUE;
									} else {
										echo "<td bgcolor='green'>X</td>";
									}
								}
							} else {
								if (($ValueSubElement <> "") && (($LongElement < $Mini) || ($LongElement > $Maxi))) {
									echo "<td bgcolor='red'>X</td>";
									$GlobalErreur = TRUE;
									$GlobalSubErreur = TRUE;
									$GlobalSubErreurMessage = "Length of Element :".$ValueSubE["ELEMENT"]." (".$LongElement.") is not on range [".$Mini."..".$Maxi."]";
								} else {
									echo "<td bgcolor='green'>X</td>";
								}
							}
							echo "</tr>";
							if ($GlobalSubErreur === TRUE) {
								echo "<tr><td bgcolor='red' colspan='8'>".$GlobalSubErreurMessage."<BR></td></tr>";							
							}
						}
						$MaxRangSubElement = $RangSubElement / 10;
						if ($CountSubElement > $MaxRangSubElement) {
							echo "<tr><td bgcolor='red' colspan='8'>DATA : ".$ValueElement."<BR>COUNT SUB-ELEMENT in data : ".$CountSubElement."<BR> Element define in directory :".$MaxRangSubElement."<BR>&nbsp;</td></tr>";
						}
						
					}
				}
				$MaxRangElement = $RangElement / 10;
				if ($CountElement > $MaxRangElement) {						
					echo "<tr><td bgcolor='red' colspan='8'>DATA : ".$this->TABLE_SEGMENT[$Name]["VALEUR"]."<BR>ELEMENT COUNT:".$CountElement."<BR> Element define in directory :".$MaxRangElement."&nbsp;</td></tr>";
				}
				}
				if ($GlobalErreur === TRUE) {
					echo "<tr><td bgcolor='red' colspan='8'>DATA : ".$this->TABLE_SEGMENT[$Name]["VALEUR"]."<BR>Control FAILED : Error exist on the segment structure</td></tr>";
				}
				echo "</tbody></table>";
			return TRUE;
		}
		
		// Get Sub Element on the TABLE_SEGMENT
		function ExtractSubElement($DataIn,$RangIn){
			$Valeur = "";
			$indice = 0;
			$maxlu  = strlen($DataIn)-1;
			$endread = FALSE;
			$CarPred = "";
			$Rang = 0;
			$RangTest = ($RangIn / 10) - 1;
			$Car = "";
			if ($RangTest > 0) {
			while ($endread === FALSE) {
				if ($indice > $maxlu) {
					$endread = TRUE;
				} else {
					$Car = $DataIn[$indice];
					$indice++;
					if (strcmp($Car,$this->UNA["SUSPENSIF"]) === 0) {
						if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
							$Valeur .= $Car;
						}
					} else {
						if (strcmp($Car,$this->UNA["COMPONENT"]) === 0) {
							if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
								$Valeur .= $Car;
							} else {
								$Rang++;
								if ($Rang === $RangTest) 
									$endread = TRUE;
							}
						} else {
							if (strcmp($Car,$this->UNA["ENDSEGMENT"]) === 0) {
								if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
									$Valeur .= $Car;
								} else {
									$endread = TRUE;
								}
							}
						}
					}
				}
				$CarPred = $Car;
			}
			}
			$Valeur = "";
			if ($Rang === $RangTest) {
				$endread = FALSE;
				while ($endread === FALSE) {	
					if ($indice > $maxlu) {
						$endread = TRUE;
					} else {
						$Car = $DataIn[$indice];
						$indice++;
						if (strcmp($Car,$this->UNA["SUSPENSIF"]) === 0) {
							if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
								$Valeur .= $Car;
							}
						} else {
							if (strcmp($Car,$this->UNA["COMPONENT"]) === 0) {
								if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
									$Valeur .= $Car;
								} else {
									$Rang++;
									if ($Rang > $RangTest) 
										$endread = TRUE;
								}
							} else {
								if (strcmp($Car,$this->UNA["ENDSEGMENT"]) === 0) {
									if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
										$Valeur .= $Car;
									} else { 
										$endread = TRUE;
									}
								} else {
									$Valeur .= $Car;
								}
							}
						}
					}
					$CarPred = $Car;
				}
			}
			return $Valeur;			
		}
		
		// Get Element on the TABLE_SEGMENT
		function ExtractElement($DataIn,$RangIn){
			$Valeur = "";
			$indice = 0;
			$maxlu  = strlen($DataIn);
			$endread = FALSE;
			$CarPred = "";
			$Rang = 0;
			$RangTest = $RangIn / 10;
			if ($maxlu === 0) {
				$endread = TRUE;
			} 
			while ($endread === FALSE) {
				if ($indice > $maxlu) {
					$endread = TRUE;
				} else {
					$Car = $DataIn[$indice];
					$indice++;
						if (strcmp($Car,$this->UNA["ELEMENT"]) === 0) {
							if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
								$Valeur .= $Car;
							} else {
								$Rang++;
								if ($Rang === $RangTest) 
									$endread = TRUE;
							}
						} else {
							if (strcmp($Car,$this->UNA["ENDSEGMENT"]) === 0) {
								if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
									$Valeur .= $Car;
								} else {
									$endread = TRUE;
								}
							}
						}
				}
				$CarPred = $Car;
			}
			$Valeur = "";
			if ($Rang === $RangTest) {
				$endread = FALSE;
				while ($endread === FALSE) {	
					if ($indice > $maxlu) {
						$endread = TRUE;
					} else {
						$Car = $DataIn[$indice];
						$indice++;
							if (strcmp($Car,$this->UNA["ELEMENT"]) === 0) {
								if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
									$Valeur .= $Car;
								} else {
									$Rang++;
									if ($Rang > $RangTest) 
										$endread = TRUE;
								}
							} else {
								if (strcmp($Car,$this->UNA["ENDSEGMENT"]) === 0) {
									if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
										$Valeur .= $Car;
									} else { 
										$endread = TRUE;
									}
								} else {
									$Valeur .= $Car;
								}
							}
					}
					$CarPred = $Car;
				}
			}
			return $Valeur;			
		}

		// Extract Segement value on the File
		function ExtractSegment() {
			$Valeur = "";
			$indice = 0;
			$maxlu  = strlen($this->TEMP)-1;
			$endread = FALSE;
			$CarPred = "";
			while ($endread === FALSE) {
				if ($indice > $maxlu) {
					$endread = TRUE;
					if ($this->TEMP = fread($this->FILENAME,4096)) {
						$indice = 0;
						$maxlu = strlen($this->TEMP);						
						$endread = FALSE;
					} else {
						$endread = TRUE;
						$Valeur = "_EOF_";
					}
				}
				if ($endread === FALSE)  {
					$Car = $this->TEMP[$indice];
					$indice++;
					if (strcmp($CarPred,$this->UNA["SUSPENSIF"]) === 0) {
						$Valeur .= $Car;
					} else {
						if (strcmp($Car,$this->UNA["ENDSEGMENT"]) === 0) {
							$Valeur .= $Car;
							$endread = TRUE;
						} else {
							if ((ord($Car) <> 10) && (ord($Car) <> 13))
								$Valeur .= $Car;
						}
					}
					$CarPred = $Car;
				}
			}

			$this->TEMP = substr($this->TEMP,$indice);
			return $Valeur;
		}
		
		// Load Segment  
		function LoadSegment($Segment) {
			$Version = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["VERSION"].$this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["RELEASE"];
			$FileSeg = fopen("./EDIFACT/".$Version."/".$Segment.".seg","r");	
			$Line = 0;
			while ($TEMP = fgets($FileSeg)) {
				$Line ++;
				if ($Line === 1) {
					$SEG         = substr($TEMP,0,3);
					$Description = substr($TEMP,7,50);
					// echo "SGEMENT:".$SEG." - DESCRIPTION :".$Description."<BR>";
					$this->AddSegment($SEG,$Description);
				} else {
					$RANG = trim(substr($TEMP,0,3));
					if ($RANG <> "") {
						$LineSub = 0;
						if ($RANG[0] === "0") {
							$RANG = substr($RANG,1,2);
						}
						$Element     = substr($TEMP,6,4);
						$Description = trim(substr($TEMP,12,43));
						$Mandatory   = trim(substr($TEMP,59,1));
						$Type = trim(strtoupper(substr($TEMP,62,2)));
						
						// echo $TEMP."Segment:".$SEG." - Rang:".$RANG." - Element:".$Element." - Mandatory:".$Mandatory." - Type:".$Type."<BR>";
						$this->AddElement2Segment($SEG,$RANG,$Element,$Mandatory);
						
						if ($Type <> "") {
							$LongMini = 0;
							$LongMaxi = 0;
							if ($Type === "AN") {
								$PointPoint = substr($TEMP,64,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,66,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,64,2);
									$LongMaxi = $LongMini;
								}
							} else {
								$Type = strtoupper(substr($TEMP,62,1));
								$PointPoint = substr($TEMP,63,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,65,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,63,2);
									$LongMaxi = $LongMini;
								}
							}
							// echo "Sub-Element:".$Element." - Type:".$Type." - Mini:".$LongMini." - Maxi:".$LongMaxi."- DESCRIPTION :".$Description."<BR>";
							$this->AddElement($Element,$Type,$LongMini,$LongMaxi,$Description); 
						} else {
							
							// echo "Element:".$Element." - DESCRIPTION :".$Description."<BR>";
							$this->AddComposite($Element,$Description);
						}
					} else {
						$SubElement = trim(substr($TEMP,6,4));
						if ($SubElement <> "") {
							$LineSub++;
							$LineSub10 = $LineSub * 10;
							$Description = trim(substr($TEMP,13,42));
							$Mandatory   = trim(substr($TEMP,50,10));
							$Type = strtoupper(substr($TEMP,62,2));
							$LongMini = 0;
							$LongMaxi = 0;
							if ($Type === "AN") {
								$PointPoint = substr($TEMP,64,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,66,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,64,2);
									$LongMaxi = $LongMini;
								}
							} else {
								$Type = strtoupper(substr($TEMP,62,1));
								$PointPoint = substr($TEMP,63,2);
								if ($PointPoint === "..") {
									$LongMaxi = substr($TEMP,65,3);
									$LongMini = 0;									
								} else {
									$LongMini = substr($TEMP,63,2);
									$LongMaxi = $LongMini;
								}
							}
							// echo "Element:".$Element." - Rang:".$LineSub10." - Sub-Element:".$SubElement." - Mandatory:".$Mandatory."<BR>";
							$this->AddElement2Composite($Element,$LineSub10,$SubElement,$Mandatory);
							// echo "Sub-Element:".$SubElement." - Type:".$Type." - Mini:".$LongMini." - Maxi:".$LongMaxi."- DESCRIPTION :".$Description."<BR>";
							$this->AddElement($SubElement,$Type,$LongMini,$LongMaxi,$Description); 
						}
					}
				}
			}
 			fclose($FileSeg);	
		}

		// $Type = GROUPE OU SEGMENT
		// $VALEUR = group_nnn ou UNH BGM...
		// $MANDATORY = M / C
		// $Mini = 1 si Mandatory=M ou 0 si Mandatory=C
		// $Maxi = nnn
		function AddMessage($SegmentNumber,$Type,$Valeur,$Mandatory,$Mini,$Maxi,$Level) {
			$this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"] = $SegmentNumber;
			$this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$SegmentNumber]=array("TYPE"=>$Type,"VALEUR"=>$Valeur,"MANDATORY"=>$Mandatory,"MINI"=>$Mini,"MAXI"=>$Maxi,"LEVEL"=>$Level,"OCCURENCE"=>0);
			$this->CurrentIndexMessage = 1;
			$this->CurrentGroupMessage = 0;
			$this->CurrentIndexGroupMessage = 0;
			// echo "MAXI_NUMBER:".$this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"]." -> ".$this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$SegmentNumber]["VALEUR"]."<BR>";
		}
		
		// INdexation du message analyse
		// TABLE_INDEXATION[N° SEGMENT] = [INDEX_MESSAGE] [LEVEL] [NAME_GROUPE] [OCCURENCE] [VALEUR_SEGMENT]
		function IndexMessage($NumSegment,$ValeurSegment) {
			// Find segment
			$this->TABLE_INDEXATION["MAXI_LINE"] = $NumSegment;
			$Name = $this->NameSegment($ValeurSegment);
			$INDEXED = TRUE;
			$FIND = FALSE;
			while ($FIND === FALSE) {
				if ($this->CurrentIndexMessage > $this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"]) {
					echo "ERREUR 1 : Current Index Message > MAXI_LINE (".$this->CurrentIndexMessage." > ".$this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"].")<br>";
					$INDEXED = FALSE; 
					return FALSE;
				}
				$Test = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$this->CurrentIndexMessage]["VALEUR"];
				$Type = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$this->CurrentIndexMessage]["TYPE"];
				$Maxi = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$this->CurrentIndexMessage]["MAXI"];
				if ($Test === $Name) {
					$this->TABLE_INDEXATION[$NumSegment]["INDEX_MESSAGE"] = $this->CurrentIndexMessage;
					$this->TABLE_INDEXATION[$NumSegment]["VALEUR"] = $ValeurSegment;
					$this->TABLE_INDEXATION[$NumSegment]["LEVEL"] = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$this->CurrentIndexMessage]["LEVEL"];
					@$this->TABLE_INDEXATION["SEGMENT"][$this->CurrentIndexMessage]["OCCURENCE"]++;
					$this->TABLE_INDEXATION[$NumSegment]["OCCURENCE"] = $this->TABLE_INDEXATION["SEGMENT"][$this->CurrentIndexMessage]["OCCURENCE"];
					$this->TABLE_INDEXATION[$NumSegment]["GROUPE"] = $this->CurrentGroupMessage;
					if ($this->CurrentGroupMessage === 0) {
						$this->CurrentIndexGroupMessage = 0;
					}
					$this->TABLE_INDEXATION[$NumSegment]["OCCURENCE_GROUPE"] = $this->CurrentIndexGroupMessage;
					if (strcmp($this->TABLE_INDEXATION[$NumSegment]["OCCURENCE"],$Maxi) === 0) {
						$this->CurrentIndexMessage++;
					}
					if ($this->TABLE_INDEXATION[$NumSegment]["OCCURENCE"] > $Maxi) {
						echo "ERREUR : OCCURENCE SURNUMERAIRE (".$Test." - MAXI:".$Maxi." - EXIST:".$this->TABLE_INDEXATION[$NumSegment]["OCCURENCE"].")<br>";
						$INDEXED = FALSE;
					}
					$FIND = TRUE;
				} else {
					$Indice = $this->CurrentIndexMessage;
					if ($Type === "GROUP") {
						$Indice++;
						$NextIndexMessage = $Indice;
						$TestNextSeg = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$NextIndexMessage]["VALEUR"];
						if ($TestNextSeg === $Name) {
							$this->CurrentGroupMessage = $Test;
							$this->CurrentIndexGroupMessage = @$this->TABLE_INDEXATION["GROUPE"][$Test]["OCCURENCE_GROUPE"];
							$this->CurrentIndexGroupMessage++;
							$this->CurrentIndexMessage = $Indice;
							$this->TABLE_INDEXATION["SEGMENT"][$this->CurrentIndexMessage]["OCCURENCE"] = 0;
							$this->TABLE_INDEXATION["GROUPE"][$Test]["OCCURENCE_GROUPE"] = $this->CurrentIndexGroupMessage;
							while ($Test <> $TestNextSeg) {
								$Indice++;
								$NextIndexMessage = $Indice;
								if ($NextIndexMessage > $this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"]) {
									$TestNextSeg = $Test;
								} else {
									$TestNextSeg = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$NextIndexMessage]["VALEUR"];
									$Groupe = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$NextIndexMessage]["TYPE"];
									$this->TABLE_INDEXATION["SEGMENT"][$NextIndexMessage]["OCCURENCE"] = 0;
									if ($Groupe === "GROUP") {
										$this->TABLE_INDEXATION["GROUPE"][$TestNextSeg]["OCCURENCE_GROUPE"] = 0;
									}
								}
							}
						} else {
							while ($Test <> $TestNextSeg) {
								$Indice++;
								$NextIndexMessage = $Indice;
								if ($NextIndexMessage > $this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"]) {
									$TestNextSeg = $Test;
								} else {
									$TestNextSeg = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$NextIndexMessage]["VALEUR"];
									$Groupe = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$NextIndexMessage]["TYPE"];
									$this->TABLE_INDEXATION["SEGMENT"][$NextIndexMessage]["OCCURENCE"] = 0;
									if ($Groupe === "GROUP") {
										$this->TABLE_INDEXATION["GROUPE"][$TestNextSeg]["OCCURENCE_GROUPE"] = 0;
										$this->CurrentGroupMessage = $TestNextSeg;
									}
								}
							}
							if ($this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$NextIndexMessage]["LEVEL"] === 0) {
								$this->CurrentGroupMessage = 0;
							}
							$this->CurrentIndexMessage = $NextIndexMessage;
							$this->CurrentIndexMessage++;
						}
					} else {
						if ($Type === "ENDGROUP")  {
							$Indice--;
							$TestPrevSeg = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$Indice]["VALEUR"];
							while ($TestPrevSeg <> $Test) {
								$Indice--;
								if ($Indice === 0) {
									$TestPrevSeg = $Test;
								} else {
									$TestPrevSeg = $this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$Indice]["VALEUR"];
								}
							}
							$this->CurrentIndexMessage = $Indice;
						} else {
							$this->CurrentIndexMessage++;
						}
						
					}
				}
				if (($FIND === FALSE) && ($this->CurrentIndexMessage > $this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"])) {
					echo "ERREUR 2 : Current Index Message > MAXI_LINE (".$this->CurrentIndexMessage." > ".$this->TABLE_MESSAGE[$this->Cpt_MESSAGE]["MAXI_INDEX"].")<br>";
					$INDEXED = FALSE; 
					$FIND = TRUE;
				}
			}
			
			return $INDEXED;
		}

		// TABLEAU INDEXATION_MESSAGE
		function DisplayIndexationMessage() {
			echo "<br><table cellspacing='1' cellpadding='2' border='1'>
			<caption><b>MESSAGE Number:".$this->Cpt_MESSAGE."&nbsp; Total Segment=".$this->TABLE_INDEXATION["MAXI_LINE"]."</b></caption>
			<thead>
			<tr>
				<th  bgcolor='green'>LINE_MESSAGE</th>
				<th  bgcolor='#ECECEC'>INDEX_MESSAGE</th>
				<th>VALUE</th>
				<th bgcolor='#CECECE'>LEVEL</th>
				<th>OCCURENCE</th>
				<th bgcolor='red'>GROUP_NAME</th>
				<th bgcolor='#FFEEFF'>OCCURENCE_GROUP</th>
			</tr>
			</thead>
			<tbody>";
			$NumSegment = 1;
			while ($NumSegment <= $this->TABLE_INDEXATION["MAXI_LINE"]) {
				echo "<TR>";
				echo "<TD>".$NumSegment."</TD>";
				echo "<TD>".$this->TABLE_INDEXATION[$NumSegment]["INDEX_MESSAGE"]."(".$this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$this->TABLE_INDEXATION[$NumSegment]["INDEX_MESSAGE"]]["VALEUR"].")</TD>";
				echo "<TD>".$this->TABLE_INDEXATION[$NumSegment]["VALEUR"]."</TD>";
				echo "<TD>".$this->TABLE_INDEXATION[$NumSegment]["LEVEL"]."</TD>";
				echo "<TD>".$this->TABLE_INDEXATION[$NumSegment]["OCCURENCE"]."</TD>";
				echo "<TD>".$this->TABLE_INDEXATION[$NumSegment]["GROUPE"]."</TD>";
				echo "<TD>".$this->TABLE_INDEXATION[$NumSegment]["OCCURENCE_GROUPE"]."</TD>";
				echo "</TR>";
				$NumSegment++;
			}
			echo "</tbody></table><br>";
		}
		
		function LoadMessage($Name,$Version,$Release) {
			
			$this->TABLE_MESSAGE[$this->Cpt_MESSAGE] = array("NAME"=>$Name,"VERSION"=>$Version,"RELEASE"=>$Release);
			$Dictionnaire = $Version.$Release;		
			$Message = $Name.".mes";
			$FileIdName = "./EDIFACT/".$Dictionnaire."/".$Message;
			if (file_exists($FileIdName) === FALSE) {
				return FALSE;
			}
			$FileMessage = fopen($FileIdName,"r");
			$Line = 0;
			$Level = 0;
			while ($TEMP = fgets($FileMessage)) {
				$Data = trim($TEMP);
				if ($Data === "[SCHEMA]") {
					while ($TEMP = fgets($FileMessage)) {
						$Line ++;
						$Mini = 0;
						$Type = "SEGMENT";
						
						$Data = trim($TEMP);
						$temporaire = explode("   ",$Data);
						$Valeur = trim($temporaire[0]);
						if ($Valeur === "group") {
							$Level++;
							$Type = strtoupper($Valeur);
							$Valeur = $temporaire[1];
							$Mandatory = $temporaire[2];
							$Maxi = $temporaire[3];
							if ($Mandatory === "M") {
								$Mini = 1;
							}
						} else {
							if ($Valeur === "endgroup") {
								$Level--;
								$Type = strtoupper($Valeur);
								$Valeur = $temporaire[1];
								$Mandatory = "C";
								$Maxi = 0;
								$Mini = 0;
							} else {
								if ($Valeur <> "") {
									$Mandatory = $temporaire[1];
									$Maxi = $temporaire[2];
									if ($Mandatory === "M") {
										$Mini = 1;
									}	
									 
									if ($Valeur[0] == "U") {
									   $this->LoadSegmentServices($this->TABLE_INTERCHANGE["INT_UNB_VERSION"],$Valeur);
									} else {   
									   $this->LoadSegment($Valeur);
									}
								}
							}
						}
						if ($Valeur <> "") {
							$this->AddMessage($Line,$Type,$Valeur,$Mandatory,$Mini,$Maxi,$Level);
						}
					} //endwhile
				}
			}
 			fclose($FileMessage);	
			return TRUE;			
		}
		
		function FindUNH($Numero) {
			
			$this->FILENAME = fopen($this->FileEDIFACT,"r");

			if ($this->TEMP = fgets($this->FILENAME,50)) {

				$this->LoadSegmentServices($this->TABLE_INTERCHANGE["INT_UNB_VERSION"],"UNH");
				$this->LoadSegmentServices($this->TABLE_INTERCHANGE["INT_UNB_VERSION"],"UNT");
				
                                $LECTURE = TRUE;
                                $Index = 0;
				while ($LECTURE) {				
					$Data = $this->ExtractSegment();
					if (strcmp($Data,"_EOF_") === 0) {
						// "_EOF_" => End file ...
						$LECTURE = FALSE; 
					} else {
						$Name = $this->NameSegment($Data);
						if (strcmp($Name,"UNH") === 0) {
							$Index++;
							$LECTURE = FALSE;
							if ($Index === $Numero) {
								$LECTURE = FALSE;
								$this->VALEURSEGMENT = $Data;
								$Test = $this->ValidSegment($this->VALEURSEGMENT);
								if ($Test === FALSE) {
									$this->DisplaySegment($this->VALEURSEGMENT);
								} else {
									$this->Cpt_MESSAGE = $Numero;
									$this->PointeurMessage = 0;
								}
							}
						} 
					}					
				}
				fclose($this->FILENAME);

				if ($Index === $Numero) {
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				fclose($this->FILENAME);
				return FALSE;
			}
		}
		
		function ValidMessage() {
			$Numero = $this->Cpt_MESSAGE;
			$this->FILENAME = fopen($this->FileEDIFACT,"r");

			if ($this->TEMP = fgets($this->FILENAME,50)) {

				$this->LoadSegmentServices($this->TABLE_INTERCHANGE["INT_UNB_VERSION"],"UNH");
				
                                $LECTURE = TRUE;
                                $Index = 0;
                                $this->CurrentIndexMessage = 1;
                                $this->CurrentGroupMessage = 0;
                                $this->CurrentIndexGroupMessage = 0;
                                @array_splice($this->TABLE_INDEXATION,0);
				while ($LECTURE) {				
					$Data = $this->ExtractSegment();
					if (strcmp($Data,"_EOF_") === 0) {
						// "_EOF_" => End file ...
						$LECTURE = FALSE; 
					} else {
						$this->VALEURSEGMENT = $Data;
						$Name = $this->NameSegment($Data);
						if (strcmp($Name,"UNH") === 0) {
							$Index++;
							if ($Index === $Numero) {
								$NumSegment = 0;
								while ($LECTURE) {
									if (strcmp($Data,"_EOF_") === 0) {
										// "_EOF_" => End file ...
										$LECTURE = FALSE; 
									} else {
										$NumSegment++;
										$this->VALEURSEGMENT = $Data;
										$Test = $this->ValidSegment($this->VALEURSEGMENT);
										if ($Test === FALSE) {
										    
										   $this->DisplaySegment($this->VALEURSEGMENT);
   										   $LECTURE = FALSE;
										} else {
											 
											$this->IndexMessage($NumSegment,$Data);
											$Name = $this->NameSegment($Data);
											if (strcmp($Name,"UNT") === 0) {
												$LECTURE = FALSE;
											} else {
												$Data = $this->ExtractSegment();
											}
										}
									}
								}
							}
						} 
					}					
				}
				fclose($this->FILENAME);
				return TRUE;
			} else {
				fclose($this->FILENAME);
				return FALSE;
			}
		}
		
		// UNH Reference
		function GetMessageReference() {
			$this->VALEURSEGMENT = $this->TABLE_SEGMENT["UNH"]["VALEUR"];
			$Data = $this->GetDataSegment("UNH.10.0062");
			return $Data;
		}
		// UNH Type
		function GetMessageType() {
			$this->VALEURSEGMENT = $this->TABLE_SEGMENT["UNH"]["VALEUR"];
			$Data = $this->GetDataSegment("UNH.20.S009.10.0065");
			return $Data;
		}
		// UNH Version
		function GetMessageVersion() {
			$this->VALEURSEGMENT = $this->TABLE_SEGMENT["UNH"]["VALEUR"];
			$Data = $this->GetDataSegment("UNH.20.S009.20.0052");
			return $Data;
		}
		// UNH Release
		function GetMessageRelease() {
			$this->VALEURSEGMENT = $this->TABLE_SEGMENT["UNH"]["VALEUR"];
			$Data = $this->GetDataSegment("UNH.20.S009.30.0054");
			return $Data;
		}
		// UNH Agency
		function GetMessageAgency() {
			$this->VALEURSEGMENT = $this->TABLE_SEGMENT["UNH"]["VALEUR"];
			$Data = $this->GetDataSegment("UNH.20.S009.40.0051");
			return $Data;
		}
		// UNH Association
		function GetMessageAssociation() {
			$this->VALEURSEGMENT = $this->TABLE_SEGMENT["UNH"]["VALEUR"];
			$Data = $this->GetDataSegment("UNH.20.S009.50.0057");
			return $Data;
		}
		
		// Read Message
		function ReadMessage() {
			$Max = @$this->TABLE_INDEXATION["MAXI_LINE"];
			if (strcmp($Max,"") === 0) {
				return FALSE;
			}
			if ($this->PointeurMessage === 0) {
				$this->PointeurMessage = 1;
				$this->CurrentIndexMessage = 1;
				$this->CurrentGroupMessage = 0;
				$this->CurrentIndexGroupMessage = 0;
			} else {
				$this->PointeurMessage++;
			}
			
			if ($this->PointeurMessage > $this->TABLE_INDEXATION["MAXI_LINE"]) {
				$this->PointeurMessage = $this->TABLE_INDEXATION["MAXI_LINE"];
				$this->VALEURSEGMENT = $this->TABLE_INDEXATION[$this->PointeurMessage]["VALEUR"];
				$this->TABLE_SEGMENT[$this->NameSegment($this->VALEURSEGMENT)]["VALEUR"] = $this->VALEURSEGMENT;
				return FALSE;
			} else {
				$this->VALEURSEGMENT = $this->TABLE_INDEXATION[$this->PointeurMessage]["VALEUR"];
				$this->TABLE_SEGMENT[$this->NameSegment($this->VALEURSEGMENT)]["VALEUR"] = $this->VALEURSEGMENT;
				return TRUE;
			}
		}
		
		// Current Group Name
		function GetCurrentGroupName() {
			return @$this->TABLE_INDEXATION[$this->PointeurMessage]["GROUPE"];
		}
		// Current Group Occurence
		function GetCurrentGroupOccurence() {
			return @$this->TABLE_INDEXATION[$this->PointeurMessage]["OCCURENCE_GROUPE"];
		}
		// Current Segment Name
		function GetCurrentSegmentName() {
			return @$this->TABLE_MESSAGE[$this->Cpt_MESSAGE][$this->TABLE_INDEXATION[$this->PointeurMessage]["INDEX_MESSAGE"]]["VALEUR"];
		}
		// Current Occurence Segment 
		function GetCurrentSegmentOccurence() {
			return @$this->TABLE_INDEXATION[$this->PointeurMessage]["OCCURENCE"];
		}
		// Get Current Data SEGMENT;  DataList example : 10.S001.20.0002
		function GetCurrentDataSegment($DataListSimple) {
			$DataList = $this->NameSegment($this->VALEURSEGMENT).".".$DataListSimple;
			$Valeur  = $this->GetDataSegment($DataList);
			return $Valeur;
		}
		
		// Valid Structure Current Segment
		function ValidCurrentSegment() {
			return $this->ValidSegment($this->VALEURSEGMENT);
		}
		
		// Display Current Segment Structure
		function DisplayCurrentSegment() {
			$this->DisplaySegment($this->VALEURSEGMENT);
		}
		
		// Ecriture file OUTPUT
		function PutBUFFER($Output,$Line,$PositionStart,$Data) {
			$this->OUTPUT[$Output][$Line][$PositionStart]=$Data;
			$Maxi = @$this->OUTPUT[$Output]["MAX_LINE"];
			if ($Line > $Maxi) {
				$this->OUTPUT[$Output]["MAX_LINE"] = $Line;
			}
		}
		
		// Get Segment Number in Message
		function GetCurrentNumberSegment() {
			return $this->PointeurMessage;
		}
		
		// file Output
		function PrintOutputFile($OutputFile) {
			$i = 1;
			$Max = @$this->OUTPUT[$OutputFile]["MAX_LINE"];
			while ($i <= $Max) {
				$Data = "";
				foreach ($this->OUTPUT[$OutputFile][$i] as $Position => $Value) {
					$D = strlen($Data);
					$V = strlen($Value);
					$P = $Position - 1;
					$END = "";
					$START = "";
					if ($D < $P) {
						$START = str_pad($Data, $P, " ");
					}
					$P = $Position + $V;
					if ($D > $P) {
						$L = $D - $P;
						$END = substr($Data, $P, $L);
					}
					$Data  = $START.$Value.$END;
				}
				$this->OUTPUT[$OutputFile]["DATA"] = $Data;
				echo "LINE ".$i." : ".$this->OUTPUT[$OutputFile]["DATA"]."<BR>";
				$i++;
			}
		}
		
		// Read No Indexation Message		
		function ReadDirectMessage() {
			 
			if ($this->PointeurMessage === 0) {
				$this->CurrentIndexMessage = 1;
				$this->CurrentGroupMessage = 0;
				$this->CurrentIndexGroupMessage = 0;
				@array_splice($this->TABLE_INDEXATION,0);
				$this->PointeurMessage = 1;
				$Numero = $this->Cpt_MESSAGE;
				@fclose($this->FILENAME);
				$this->FILENAME = fopen($this->FileEDIFACT,"r");

				if ($this->TEMP = fgets($this->FILENAME,50)) {

					$this->LoadSegmentServices($this->TABLE_INTERCHANGE["INT_UNB_VERSION"],"UNH");
				
					$LECTURE = TRUE;
					$Index = 0;
					while ($LECTURE) {				
						$Data = $this->ExtractSegment();
						if (strcmp($Data,"_EOF_") === 0) {
							fclose($this->FILENAME);
							$LECTURE = FALSE; 
							return FALSE;
						} else {
							$this->VALEURSEGMENT = $Data;
							$Name = $this->NameSegment($Data);
							if (strcmp($Name,"UNH") === 0) {
								$Index++;
								if ($Index === $Numero) {
									$NumSegment = 0;
									while ($LECTURE) {
										 
										if (strcmp($Data,"_EOF_") === 0) {
											// "_EOF_" => End file ...
											fclose($this->FILENAME);
											$LECTURE = FALSE;
											return FALSE;
										} else {
											$NumSegment++;
											 
											if ($NumSegment === $this->PointeurMessage) {
												$this->VALEURSEGMENT = $Data;
												 
												$Test = $this->ValidSegment($this->VALEURSEGMENT);
												if ($Test === FALSE) {
													$this->DisplaySegment($this->VALEURSEGMENT);
													$LECTURE = FALSE;
													fclose($this->FILENAME);
													return FALSE;
												} else {
													$Test = $this->IndexMessage($this->PointeurMessage,$Data);
													 
													if ($Test === FALSE) {
														$LECTURE = FALSE;
														fclose($this->FILENAME);
														return FALSE;
													}
												}
												$Name = $this->NameSegment($Data);
												if (strcmp($Name,"UNT") === 0) {
													$LECTURE = FALSE;
												}
												$LECTURE = FALSE;
												return TRUE;
											} else {
												$Data = $this->ExtractSegment();
											}
										}
									}
								}
							} 
						}					
					}
				}
				fclose($this->FILENAME);
				return FALSE;
			} else {
				$Test = $this->ValidSegment($this->VALEURSEGMENT);
				if (strcmp($Test,"UNT") === 0) {
					fclose($this->FILENAME);
					return FALSE;
				}
				$Data = $this->ExtractSegment();
				if (strcmp($Data,"_EOF_") === 0) {
					fclose($this->FILENAME);
					$LECTURE = FALSE;
				}
				$this->PointeurMessage++;
				$this->VALEURSEGMENT = $Data;
				$Test = $this->ValidSegment($this->VALEURSEGMENT);
				if ($Test === FALSE) {
					$this->DisplaySegment($this->VALEURSEGMENT);
					fclose($this->FILENAME);
					return FALSE;
				} else {
					$Test = $this->IndexMessage($this->PointeurMessage,$Data);
					if ($Test === FALSE) {
						return FALSE;
					}
				}
				$Name = $this->NameSegment($Data);
				if (strcmp($Name,"UNT") === 0) {
					fclose($this->FILENAME);
					return TRUE;
				} else {
					return TRUE;
				}
			}
			return TRUE;
		}
		
		// Display CLASS_EDIFACT information
		function DisplayClassEdifactInformation() {
			$Version = $this->CLASS_EDIFACT["VERSION"];
			$DateVersion = $this->CLASS_EDIFACT["DATE_UPDATE"];
			$Description = $this->CLASS_EDIFACT["DESCRIPTION"];
			echo "<BR><B>CLASS EDIFACT:</B><BR>";
			echo "<BR><B>VERSION:</B>".$Version."<BR>";
			echo "<BR><B>DATE UPDATE:</B>".$DateVersion."<BR>";
			echo "<BR><B>DESCRIPTION:</B><BR>".$Description."<BR><BR>";
		}
	}
                                                       
$_EDIFACT_ = 1;

}

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

$time_start = microtime_float();


$E = new EDIFACT();

$E->DisplayClassEdifactInformation();

$E->LoadFile("EDIFACT.txt");

$OK = $E->FindUNH(1);
echo "FIND UNH(1):".$OK."<BR>";
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
// echo "TABLE MESSAGE INFO<BR>";
// print_r($E->TABLE_MESSAGE);

echo "**** VALIDATION MESSAGE *** <BR>";
// $E->ValidMessage();
echo "**** FIN VALIDATION MESSAGE *** <BR>";

/***
echo "<BR>TABLE SEGMENT :<BR>";
print_r($E->TABLE_SEGMENT);
echo "<BR>TABLE COMPOSITE ELEMENT :<BR>";
print_r($E->TABLE_COMPOSITE_ELEMENT);
echo "<BR>TABLE ELEMENT :<BR>";
print_r($E->TABLE_ELEMENT);
***/

echo "<BR>";
echo "TABLE INTERCHANGE INFO<BR>";
print_r($E->TABLE_INTERCHANGE);
echo "<BR><BR>";
echo "TABLE SEGMENT UNx:<BR>";
print_r($E->TABLE_SEGMENT["UNB"]);
echo "<BR>";
print_r($E->TABLE_SEGMENT["UNH"]);
echo "<BR>";
print_r($E->TABLE_SEGMENT["UNT"]);
echo "<BR>";
print_r($E->TABLE_SEGMENT["UNZ"]);
echo "<BR>";
echo "<BR>";

// echo "TABLE MESSAGE INFO<BR>";
// print_r($E->TABLE_MESSAGE);
// print_r($E->TABLE_INDEXATION);

// $E->DisplayIndexationMessage();

while ($E->ReadDirectMessage()) {
	echo "Segment Number:".$E->GetCurrentNumberSegment()." => ".$E->GetCurrentSegmentName()." => ".$E->GetCurrentSegmentOccurence()." GROUP:".$E->GetCurrentGroupName()." => ".$E->GetCurrentGroupOccurence()."<BR>";;
	
	// Recherche NAD+IV dans le groupe "2"
	if (strcmp($E->GetCurrentGroupName(),"2") === 0) {
			if (strcmp($E->GetCurrentSegmentName(),"NAD") === 0) {
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
					$E->ValidCurrentSegment();
					$E->DisplayCurrentSegment();
				}
			}
	}
	
}

echo "END Segment Number:".$E->GetCurrentNumberSegment()." => ".$E->GetCurrentSegmentName()." => ".$E->GetCurrentSegmentOccurence()." GROUP:".$E->GetCurrentGroupName()." => ".$E->GetCurrentGroupOccurence()."<BR>";;

$E->PrintOutputFile("FACTURE");

$time_end = microtime_float();
$time = $time_end - $time_start;
echo "TEMP DE REACTION $time secondes\n";

?>