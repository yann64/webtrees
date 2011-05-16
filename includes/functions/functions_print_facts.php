<?php
// Function for printing facts
//
// Various printing functions used to print fact records
//
// webtrees: Web based Family History software
// Copyright (C) 2011 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2002 to 2010  PGV Development Team.  All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//
// $Id$

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

define('WT_FUNCTIONS_PRINT_FACTS_PHP', '');

// print a fact record, for the gedcom object pages.
function print_fact(WT_Event $eventObj) {
	global $TEXT_DIRECTION, $HIDE_GEDCOM_ERRORS, $SHOW_FACT_ICONS, $SHOW_MEDIA_FILENAME;
	global $n_chil, $n_gchi, $SEARCH_SPIDER;

	if (!$eventObj->canShow()) {
		return;
	}

	$noedit   = !$eventObj->canEdit();
	$fact     = $eventObj->getTag();
	$rawEvent = $eventObj->getDetail();
	$event    = htmlspecialchars($rawEvent);
	$factrec  = $eventObj->getGedcomRecord();
	$linenum  = $eventObj->getLineNumber();
	$parent   = $eventObj->getParentObject();
	
	if (!is_null($eventObj->getFamilyId())) {
		$pid = $eventObj->getFamilyId();
	} elseif (!is_null($parent)) {
		$pid = $parent->getXref();
	} else {
		$pid = '';
	}

	// Some facts don't get printed here ...
	switch ($fact) {
	case 'NOTE':
		print_main_notes($factrec, 1, $pid, $linenum, $noedit);
		return;
	case 'SOUR':
		print_main_sources($factrec, 1, $pid, $linenum, $noedit);
		return;
	case 'OBJE':
		// These are printed separately, after all other facts
		return;
	case 'BLOB':
		// A deprecated tag, that cannot be displayed ??
		return;
	case 'FAMC':
	case 'FAMS':
	case 'CHIL':
	case 'HUSB':
	case 'WIFE':
		// These are internal links, not facts
		return;
	default:
		// Hide unrecognised/custom tags?
		if ($HIDE_GEDCOM_ERRORS && !WT_Gedcom_Tag::isTag($fact)) {
			return;
		}
		break;
	}

	// Who is this fact about?  Need it to translate fact label correctly
	if (preg_match('/2 ASSO @('.WT_REGEX_XREF.')@/', $factrec, $match)) {
		// Event of close relative
		$label_person=WT_Person::getInstance($match[1]);
	} else if (preg_match('/2 _WTS @('.WT_REGEX_XREF.')@/', $factrec, $match)) {
		// Event of close relative
		$label_person=WT_Person::getInstance($match[1]);
	} else if ($parent instanceof WT_Family) {
		// Family event
		$husb = $parent->getHusband();
		$wife = $parent->getWife();
		if (empty($wife) && !empty($husb)) $label_person=$husb;
		else if (empty($husb) && !empty($wife)) $label_person=$wife;
		else $label_person=$parent;
	} else {
		// The actual person
		$label_person=$parent;
	}

	$styleadd="";
	if (strpos($factrec, "WT_NEW")!==false) $styleadd="change_new";
	if (strpos($factrec, "WT_OLD")!==false) $styleadd="change_old";

	if ($linenum<1) $styleadd='rela'; // not editable
	if ($linenum==-1) $styleadd='histo'; // historical facts

	if ($styleadd=='') {
		$rowID = 'row_'.floor(microtime()*1000000);
	} else {
		$rowID = 'row_'.$styleadd;
	}

	// Does this fact have a type?
	if (preg_match('/\n2 TYPE (.+)/', $factrec, $match)) {
		$type=$match[1];
	} else {
		$type='';
	}

	switch ($fact) {
	case 'EVEN':
	case 'FACT':
		if ($type=='image_size') {
			// The media page generates dummy "1 EVEN/2 TYPE image_size" facts
			$label=WT_I18N::translate('Image Dimensions');
		} elseif ($type=='file_size') {
			// The media page generates dummy "1 EVEN/2 TYPE file_size" facts
			$label=WT_I18N::translate('File Size');
		} elseif (WT_Gedcom_Tag::isTag($type)) {
			// Some users (just Meliza?) use "1 EVEN/2 TYPE BIRT".  Translate the TYPE.
			$label=WT_Gedcom_Tag::getLabel($type, $label_person);
		} elseif ($type) {
			// We don't have a translation for $type - but a custom translation might exist.
			$label=WT_I18N::translate(htmlspecialchars($type));
		} else {
			// An unspecified fact/event
			$label=WT_Gedcom_Tag::getLabel($fact, $label_person);
		}
		break;
	case 'MARR':
		// This is a hack for a proprietory extension.  Is it still used/needed?
		if ($type=='CIVIL' || $type=='PARTNERS' || $type=='RELIGIOUS' || $type=='UNKNOWN') {
			$label=WT_Gedcom_Tag::getLabel('MARR_'.$type, $label_person);
			$type='';
		} else {
			$label=WT_Gedcom_Tag::getLabel($fact, $label_person);
		}
		break;
	default:
		// Normal fact/event
		$label=WT_Gedcom_Tag::getLabel($fact, $label_person);
		break;
	}

	echo '<tr class="', $rowID, '">';
	echo '<td class="descriptionbox ', $styleadd, ' width20">';

	if ($SHOW_FACT_ICONS) {
		echo $eventObj->Icon(), ' ';
	}

	if (WT_USER_CAN_EDIT && $styleadd!='change_old' && $linenum>0 && $eventObj->canEdit()) {
		echo
			'<a onclick="return edit_record(\'', $pid, '\', ', $linenum, ');" href="javascript:;" title="', WT_I18N::translate('Edit'), '">',  $label,  '</a>',
			'<div class="editfacts">',
			'<div class="editlink"><a onclick="return edit_record(\'', $pid, '\', ', $linenum, ');" href="javascript:;" title="', WT_I18N::translate('Edit'), '"><span class="link_text">', WT_I18N::translate('Edit'), '"</span></a></div>',
			'<div class="copylink"><a onclick="return copy_record(\'', $pid, '\', ', $linenum, ');" href="javascript:;" title="', WT_I18N::translate('Copy'), '"><span class="link_text">', WT_I18N::translate('Copy'), '"</span></a></div>',
			'<div class="deletelink"><a onclick="return delete_record(\'', $pid, '\', ', $linenum, ');" href="javascript:;" title="', WT_I18N::translate('Delete'), '"><span class="link_text">', WT_I18N::translate('Delete'), '"</span></a></div>',
			'</div>';
	} else {
		echo $label;
	}

	switch ($fact) {
	case '_BIRT_CHIL':
		echo '<br />', WT_I18N::translate('#%d', $n_chil++);
		break;
	case '_BIRT_GCHI':
	case '_BIRT_GCH1':
	case '_BIRT_GCH2':
		echo '<br />', WT_I18N::translate('#%d', $n_gchi++);
		break;
	}

	echo '</td><td class="optionbox ', $styleadd, ' wrap">';

	// Display a "2 TYPE xxx" - except for FACT/EVEN, as we have already displayed this above.
	if ($fact!='FACT' && $fact!='EVEN' && $type) {
		// We don't have a translation for $type - but a custom translation might exist.
		echo  WT_I18N::translate(htmlspecialchars($type));
		echo '<br />';
	}

	//-- print spouse name for marriage events
	if (preg_match('/_WTS @(.*)@/', $factrec, $match)) {
		$spouse=WT_Person::getInstance($match[1]);
		if ($spouse) {
			echo ' <a href="', $spouse->getHtmlUrl(), '">';
			if ($spouse->canDisplayName()) {
				echo PrintReady($spouse->getFullName());
			} else {
				echo WT_I18N::translate('Private');
			}
			echo '</a>';
		}
		$family = WT_Family::getInstance($pid);
		if ($family) {
			if ($spouse) echo ' - ';
			echo '<a href="', $family->getHtmlUrl(), '">', WT_I18N::translate('View Family'), '</a>';
			echo '<br />';
		}
	}
	// -- find date for each fact
	echo format_fact_date($eventObj, true, true);
	//-- print other characterizing fact information
	if ($event!="" && $fact!="ASSO") {
		echo " ";
		$ct = preg_match("/@(.*)@/", $event, $match);
		if ($ct>0) {
			$gedrec=WT_GedcomRecord::getInstance($match[1]);
			if (is_object($gedrec)) {
				if ($gedrec->getType()=='INDI') {
					echo '<a href="', $gedrec->getHtmlUrl(), '">', $gedrec->getFullName(), '</a><br />';
				} elseif ($fact=='REPO') {
					print_repository_record($match[1]);
				} else {
					print_submitter_info($match[1]);
				}
			}
		}
		else if ($fact=="ALIA") {
			//-- strip // from ALIA tag for FTM generated gedcoms
			echo preg_replace("'/'", "", $event), "<br />";
		}
		elseif ($event=="N") {
			if (get_sub_record(2, "2 DATE", $factrec)=="") {
				echo WT_I18N::translate('No');
			}
		} elseif (strstr("URL WWW ", $fact." ")) {
			echo "<a href=\"", $event, "\" target=\"new\">", PrintReady($event), "</a>";
		} elseif (strstr("_EMAIL", $fact)) {
			echo "<a href=\"mailto:", $event, "\">", $event, "</a>";
		} elseif (strstr("AFN", $fact)) {
			echo '<a href="http://www.familysearch.org/Eng/Search/customsearchresults.asp?LDS=0&file_number=', urlencode($event), '" target="new">', $event, '</a>';
		} elseif (strstr('FAX PHON ', $fact.' ')) {
			echo getLRM(), $event, ' ' , getLRM();
		} elseif (strstr('FILE ', $fact.' ')) {
			if ($SHOW_MEDIA_FILENAME || WT_USER_GEDCOM_ADMIN) echo getLRM(), $event, ' ' , getLRM();
		} elseif ($fact=='RESN') {
			echo '<span class="field">';
			switch ($event) {
			case 'none':
				// Note: "1 RESN none" is not valid gedcom, and the GUI will not let you add it.
				// However, webtrees privacy rules will interpret it as "show an otherwise private record to public".
				echo '<img src="images/RESN_none.gif" /> ', WT_I18N::translate('Show to visitors');
				break;
			case 'privacy':
				echo '<img src="images/RESN_privacy.gif" /> ', WT_I18N::translate('Show to members');
				break;
			case 'confidential':
				echo '<img src="images/RESN_confidential.gif" /> ', WT_I18N::translate('Show to managers');
				break;
			case 'locked':
				echo '<img src="images/RESN_locked.gif" /> ', WT_I18N::translate('Only managers can edit');
				break;
			default:
				echo $event;
				break;
			}
			echo '</span>';
		} elseif ($event!='Y') {
			if (!strstr('ADDR _CREM ', substr($fact, 0, 5).' ')) {
				if ($fact=='EVEN' && ($type=='file_size' || $type=='image_size')) {
					echo PrintReady($rawEvent);
				} else {
					echo PrintReady($event);
				}
			}
		}
		$temp = trim(get_cont(2, $factrec));
		if (strstr("PHON ADDR ", $fact." ")===false && $temp!="") {
			echo PrintReady($temp);
		}
	}
	// Place (includes temple)
	echo '<div class="place">', format_fact_place($eventObj, true, true, true), '</div>';
	// Address
	if ($fact!="ADDR") {
		print_address_structure($factrec, 2);
	} else {
		print_address_structure($factrec, 1);
	}
	// Associates
	print_asso_rela_record($eventObj);

	// Display any other "2 XXXX" attributes in the order in which they appear
	preg_match_all('/\n2 ('.WT_REGEX_TAG.') (.+)/', $factrec, $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		switch ($match[1]) {
		case 'DATE':
		case 'TIME':
		case 'AGE':
		case 'PLAC':
		case 'ADDR':
		case 'ALIA':
		case 'ASSO':
		case 'DESC':
		case 'EMAIL':
		case 'FAX':
		case 'PHON':
		case 'RELA':
		case 'STAT':
		case 'TEMP':
		case 'TYPE':
		case 'WWW':
		case '_EMAIL':
		case 'URL':
		case 'FAMS':
		case '_WTS':
		case '_WTFS':
		case 'CONT':
			// These were already shown at the beginning
			break;
		case 'NOTE':
		case 'OBJE':
		case 'SOUR':
			// These will be shown at the end
			break;
		case 'EVEN': // 0 SOUR / 1 DATA / 2 EVEN /3 DATE/PLAC
			echo '<div>';
			foreach (preg_split('/ *, */', $match[2]) as $n=>$value) {
				if ($n>0) {
					echo ', ';
				}
				if (preg_match("/2 EVEN (.*)/", $factrec)>0) {
					echo '<span class="label">', WT_Gedcom_Tag::getLabel('EVEN'), ':</span> <span class="field">', $value, '</span>';
				} else {
					echo $value;
				}
			}
			if (preg_match('/\n3 DATE (.+)/', $factrec, $date_match)) {
				$date=new WT_Date($date_match[1]);
				echo ' - ', $date->Display();
			}
			if (preg_match('/\n3 PLAC (.+)/', $factrec, $plac_match)) {
				echo ' - ', $plac_match[1];
			}
			echo '</div>';
			break;
		case 'FAMC': // 1 ADOP / 2 FAMC
			echo '<div><span class="label">', WT_Gedcom_Tag::getLabel('FAMC'), ':</span> ';
			$family=WT_Family::getInstance(str_replace('@', '', $match[2]));
			if ($family) { // May be a pointer to a non-existant record
				echo '<a href="', $family->getHtmlUrl(), '">', $family->getFullName(), '</a>';
			} else {
				echo '<span class="error">', $match[2], '</span>';
			}
			if (preg_match('/\n3 ADOP (HUSB|WIFE|BOTH)/', $factrec, $match)) {
				echo '<div><span class="indent"><span class="label">', WT_Gedcom_Tag::getLabel('ADOP'), ':</span> <span class="field">';
				switch ($match[1]) {
				case 'HUSB':
				case 'WIFE':
					echo WT_Gedcom_Tag::getLabel($match[1]);
					break;
				case 'BOTH':
					echo WT_Gedcom_Tag::getLabel('HUSB'), '+', WT_Gedcom_Tag::getLabel('WIFE');
					break;
				}
				echo '</span>';
			}
			echo '</div>';
			break;
		case '_WT_USER':
			$fullname=getUserFullname(getUserId($match[2])); // may not exist	
			echo '<div> - ', WT_Gedcom_Tag::getLabel('_WT_USER'), ': ';
			if ($fullname) {
				echo '<span title="'.htmlspecialchars($fullname).'">'.$match[2].'</span>';
			} else {
				echo $match[2];
			}
			echo '</div>';
			break;
		case 'RESN':
			echo '<div><span class="label">', WT_Gedcom_Tag::getLabel('RESN'), ':</span> <span class="field">';
			switch ($match[2]) {
			case 'none':
				// Note: "2 RESN none" is not valid gedcom, and the GUI will not let you add it.
				// However, webtrees privacy rules will interpret it as "show an otherwise private fact to public".
				echo '<img src="images/RESN_none.gif" /> ', WT_I18N::translate('Show to visitors');
				break;
			case 'privacy':
				echo '<img src="images/RESN_privacy.gif" /> ', WT_I18N::translate('Show to members');
				break;
			case 'confidential':
				echo '<img src="images/RESN_confidential.gif" /> ', WT_I18N::translate('Show to managers');
				break;
			case 'locked':
				echo '<img src="images/RESN_locked.gif" /> ', WT_I18N::translate('Only managers can edit');
				break;
			default:
				echo $match;
				break;
			}
			echo '</span></div>';
			break;
		case 'CALN':
			echo '<div><span class="label">', WT_Gedcom_Tag::getLabel($match[1]), ':</span> <span class="field">', expand_urls($match[2]), '</span></div>';
			break;
		default:
			if (!$HIDE_GEDCOM_ERRORS || WT_Gedcom_Tag::isTag($match[1])) {
				echo '<div>';
				$label = WT_Gedcom_Tag::getLabel($fact.':'.$match[1], $label_person);
				if ($SHOW_FACT_ICONS && file_exists(WT_THEME_DIR."images/facts/".$match[1].".gif")) {
					echo '<img src="', WT_THEME_DIR, 'images/facts/', $match[1], '.gif" alt="', $label, '" title="', $label, '" align="middle" /> ';
				} else {
					echo '<span class="label">', $label, ':</span> ';
				}
				echo htmlspecialchars($match[2]);
				$sub_rec = get_sub_record(2, '2 '.$fact, $factrec, 1);
				$tmp=new WT_Date(get_gedcom_value('DATE', 3, $sub_rec, $truncate='', $convert=false));
				if ($tmp->Display(true)!='&nbsp;') echo ' - '.$tmp->Display(true);
				echo '</div>';
			}
			break;
		}
	}
	echo '<br/>';
	// -- find source for each fact
	print_fact_sources($factrec, 2);
	// -- find notes for each fact
	print_fact_notes($factrec, 2);
	//-- find multimedia objects
	print_media_links($factrec, 2, $pid);
	echo '</td></tr>';
}
//------------------- end print fact function

/**
 * print a submitter record
 *
 * find and print submitter information
 * @param string $sid  the Gedcom Xref ID of the submitter to print
 */
function print_submitter_info($sid) {
	global $GEDCOM;
	$ged_id=get_id_from_gedcom($GEDCOM);
	$srec = find_gedcom_record($sid, $ged_id);
	preg_match("/1 NAME (.*)/", $srec, $match);
	// PAF creates REPO record without a name
	// Check here if REPO NAME exists or not
	if (isset($match[1])) echo $match[1], "<br />";
	print_address_structure($srec, 1);
	print_media_links($srec, 1);
}

/**
 * print a repository record
 *
 * find and print repository information attached to a source
 * @param string $sid  the Gedcom Xref ID of the repository to print
 */
function print_repository_record($xref) {
	$repository=WT_Repository::getInstance($xref);
	if ($repository && $repository->canDisplayDetails()) {
		echo '<a class="field" href="', $repository->getHtmlUrl(), '">', $repository->getFullName(), '</a><br />';
		print_address_structure($repository->getGedcomRecord(), 1);
		echo '<br />';
		print_fact_notes($repository->getGedcomRecord(), 1);
	}
}

/**
 * print a source linked to a fact (2 SOUR)
 *
 * this function is called by the print_fact function and other functions to
 * print any source information attached to the fact
 * @param string $factrec The fact record to look for sources in
 * @param int $level The level to look for sources at
 * @param boolean $return whether to return the data or print the data
 */
function print_fact_sources($factrec, $level, $return=false) {
	global $WT_IMAGES, $EXPAND_SOURCES;

	$data = "";
	$nlevel = $level+1;

	// -- Systems not using source records [ 1046971 ]
	$ct = preg_match_all("/$level SOUR (.*)/", $factrec, $match, PREG_SET_ORDER);
	for ($j=0; $j<$ct; $j++) {
		if (strpos($match[$j][1], "@")===false) {
			$srec = get_sub_record($level, "$level SOUR ", $factrec, $j+1);
			$srec = substr($srec, 6); // remove "2 SOUR"
			$srec = str_replace("\n".($level+1)." CONT ", "<br/>", $srec); // remove n+1 CONT
			$data .= "<div=\"fact_SOUR\"><span class=\"label\">".WT_I18N::translate('Source').":</span> <span class=\"field\">".PrintReady($srec)."</span></div>";
		}
	}
	// -- find source for each fact
	$ct = preg_match_all("/$level SOUR @(.*)@/", $factrec, $match, PREG_SET_ORDER);
	$spos2 = 0;
	for ($j=0; $j<$ct; $j++) {
		$sid = $match[$j][1];
		$source=WT_Source::getInstance($sid);
		if ($source) {
			if ($source->canDisplayDetails()) {
				$spos1 = strpos($factrec, "$level SOUR @".$sid."@", $spos2);
				$spos2 = strpos($factrec, "\n$level", $spos1);
				if (!$spos2) {
					$spos2 = strlen($factrec);
				}
				$srec = substr($factrec, $spos1, $spos2-$spos1);
				$lt = preg_match_all("/$nlevel \w+/", $srec, $matches);
				$data .= "<div class=\"fact_SOUR\">";
				$data .= "<span class=\"label\">";
				$elementID = $sid."-".floor(microtime()*1000000);
				if ($EXPAND_SOURCES) {
					$plusminus="minus";
				} else {
					$plusminus="plus";
				}
				if ($lt>0) {
					$data .= "<a href=\"javascript:;\" onclick=\"expand_layer('$elementID'); return false;\"><img id=\"{$elementID}_img\" src=\"".$WT_IMAGES[$plusminus]."\" border=\"0\" width=\"11\" height=\"11\" alt=\"";
					if ($plusminus=="plus") $data .= WT_I18N::translate('Show Details')."\" title=\"".WT_I18N::translate('Show Details')."\" /></a> ";
					else $data .= WT_I18N::translate('Hide Details')."\" title=\"".WT_I18N::translate('Hide Details')."\" /></a> ";
				}
				$data .= WT_I18N::translate('Source').":</span> <span class=\"field\">";
				$data .= "<a href=\"".$source->getHtmlUrl()."\">".PrintReady($source->getFullName())."</a>";
				$data .= "</span></div>";
	
				$data .= "<div id=\"$elementID\"";
				if ($EXPAND_SOURCES) {
					$data .= " style=\"display:block\"";
				}
				$data .= " class=\"source_citations\">";
				// PUBL
				$text = get_gedcom_value("PUBL", "1", $source->getGedcomRecord());
				if (!empty($text)) {
					$data .= "<span class=\"label\">".WT_Gedcom_Tag::getLabel('PUBL').": </span>";
					$data .= $text;
				}
				$data .= printSourceStructure(getSourceStructure($srec));
				$data .= "<div class=\"indent\">";
				ob_start();
				print_media_links($srec, $nlevel);
				$data .= ob_get_clean();
				$data .= print_fact_notes($srec, $nlevel, false, true);
				$data .= "</div>";
				$data .= "</div>";
			}
		} else {
			$data='<div class="fact_SOUR"><span class="label">'.WT_I18N::translate('Source').'</span>: <span class="field">'.$sid.'</span></div>';
		}
	}

	if ($return) {
		return $data;
	} else {
		echo $data;
	}	
}

//-- Print the links to multi-media objects
function print_media_links($factrec, $level, $pid='') {
	global $MULTI_MEDIA, $TEXT_DIRECTION;
	global $SEARCH_SPIDER;
	global $THUMBNAIL_WIDTH, $USE_MEDIA_VIEWER;
	global $LB_URL_WIDTH, $LB_URL_HEIGHT;
	global $GEDCOM;
	$ged_id=get_id_from_gedcom($GEDCOM);
	if (!$MULTI_MEDIA) return;
	$nlevel = $level+1;
	if ($level==1) $size=50;
	else $size=25;
	if (preg_match_all("/$level OBJE @(.*)@/", $factrec, $omatch, PREG_SET_ORDER) == 0) return;
	$objectNum = 0;
	while ($objectNum < count($omatch)) {
		$media_id = $omatch[$objectNum][1];
		$row=
			WT_DB::prepare("SELECT m_titl, m_file, m_gedrec FROM `##media` where m_media=? AND m_gedfile=?")
			->execute(array($media_id, WT_GED_ID))
			->fetchOneRow(PDO::FETCH_ASSOC);
		if (WT_Media::getInstance($media_id)->canDisplayDetails()) {
			// A new record, pending acceptance?
			if (!$row && WT_USER_CAN_EDIT) {
				$mediarec = find_updated_record($media_id, $ged_id);
				$row["m_file"] = get_gedcom_value("FILE", 1, $mediarec);
				$row["m_titl"] = get_gedcom_value("TITL", 1, $mediarec);
				if (empty($row["m_titl"])) $row["m_titl"] = get_gedcom_value("FILE:TITL", 1, $mediarec);
				$row["m_gedrec"] = $mediarec;
			}

			$mainMedia = check_media_depth($row["m_file"], "NOTRUNC");
			$thumbnail = thumbnail_file($mainMedia, true, false, $pid);
			$isExternal = isFileExternal($row["m_file"]);
			$mediaTitle = $row["m_titl"];

			// Determine the size of the mediafile
			$imgsize = findImageSize($mainMedia);
			$imgwidth = $imgsize[0]+40;
			$imgheight = $imgsize[1]+150;
			if ($objectNum > 0) echo "<br clear=\"all\" />";
			echo "<table><tr><td>";
			if ($isExternal || media_exists($thumbnail)) {

				//LBox --------  change for Lightbox Album --------------------------------------------
				if (WT_USE_LIGHTBOX && preg_match("/\.(jpe?g|gif|png)$/i", $mainMedia)) {
					$name = trim($row["m_titl"]);
					echo "<a href=\"" . $mainMedia . "\" rel=\"clearbox[general_1]\" rev=\"" . $media_id . "::" . $GEDCOM . "::" . PrintReady(htmlspecialchars($name)) . "\">";
				} else if (WT_USE_LIGHTBOX && preg_match("/\.(pdf|avi|txt)$/i", $mainMedia)) {
					require_once WT_ROOT.WT_MODULES_DIR.'lightbox/lb_defaultconfig.php';
					$name = trim($row["m_titl"]);
					echo "<a href=\"" . $mainMedia . "\" rel='clearbox({$LB_URL_WIDTH}, {$LB_URL_HEIGHT}, click)' rev=\"" . $media_id . "::" . $GEDCOM . "::" . PrintReady(htmlspecialchars($name)) . "\">";
				// extra for Streetview ----------------------------------------
				} else if (WT_USE_LIGHTBOX && strpos($row["m_file"], 'http://maps.google.')===0) {
					echo '<iframe style="float:left; padding:5px;" width="264" height="176" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="', $row["m_file"], '&amp;output=svembed"></iframe>';
				// --------------------------------------------------------------------------------------
				} else if ($USE_MEDIA_VIEWER) {
					echo "<a href=\"mediaviewer.php?mid={$media_id}\">";
				} else if (preg_match("/\.(jpe?g|gif|png)$/i", $mainMedia)) {
					echo "<a href=\"javascript:;\" onclick=\"return openImage('", rawurlencode($mainMedia), "', $imgwidth, $imgheight);\">";
				// extra for Streetview ----------------------------------------
				} else if (strpos($row["m_file"], 'http://maps.google.')===0) {
					echo '<iframe width="300" height="200" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="', $row["m_file"], '&amp;output=svembed"></iframe>';
				} else {
					echo "<a href=\"mediaviewer.php?mid={$media_id}\">";
				}

				echo "<img src=\"", $thumbnail, "\" border=\"0\" align=\"" , $TEXT_DIRECTION== "rtl"?"right": "left" , "\" class=\"thumbnail\"";
				if (strpos($mainMedia, 'http://maps.google.')===0) {
					// Do not print Streetview title here (PF&D tab)
				} else {
					if ($isExternal) echo " width=\"", $THUMBNAIL_WIDTH, "\"";
					echo " alt=\"" . PrintReady($mediaTitle) . "\"";
				}
				//LBox --------  change for Lightbox Album --------------------------------------------
				if ($row["m_titl"]) {
					echo " title=\"" . $row["m_titl"] . "\"";
				} else {
					echo " title=\"" . basename($row["m_file"]) . "\"";
				}
				// ---------------------------------------------------------------------------------------------
				echo "/>";
				echo "</a>";
			}
			echo "</td><td>";
			if (empty($SEARCH_SPIDER)) {
				echo "<a href=\"mediaviewer.php?mid={$media_id}\">";
			}
			if ($TEXT_DIRECTION=="rtl" && !hasRTLText($mediaTitle)) echo "<em>" , getLRM() ,  PrintReady($mediaTitle), "</em>";
			else echo "<em>", PrintReady($mediaTitle), "</em><br />";
			if (empty($SEARCH_SPIDER)) {
				echo "</a>";
			}
			// NOTE: echo the notes of the media
			echo print_fact_notes($row["m_gedrec"], 1);
			// NOTE: echo the format of the media
			if (!empty($row["m_ext"])) {
				echo "<br /><span class=\"label\">", WT_Gedcom_Tag::getLabel('FORM'), ": </span> <span class=\"field\">", $row["m_ext"], "</span>";
				if ($imgsize[2]!==false) {
					echo "<span class=\"label\"><br />", WT_I18N::translate('Image Dimensions'), ": </span> <span class=\"field\" style=\"direction: ltr;\">" , $imgsize[0] , ($TEXT_DIRECTION =="rtl"?(" " . getRLM() . "x" . getRLM() . " ") : " x ") , $imgsize[1] , "</span>";
				}
			}
			if (preg_match('/2 DATE (.+)/', get_sub_record("FILE", 1, $row["m_gedrec"]), $match)) {
				$media_date=new WT_Date($match[1]);
				$md = $media_date->Display(true);
				echo "<br /><span class=\"label\">", WT_Gedcom_Tag::getLabel('DATE'), ": </span> ", $md;
			}
			$ttype = preg_match("/".($nlevel+1)." TYPE (.*)/", $row["m_gedrec"], $match);
			if ($ttype>0) {
				$mediaType = WT_Gedcom_Tag::getFileFormTypeValue($match[1]);
				echo "<br /><span class=\"label\">", WT_I18N::translate('Type'), ": </span> <span class=\"field\">$mediaType</span>";
			}
			//echo "</span>";
			echo "<br />";
			//-- print spouse name for marriage events
			$ct = preg_match("/WT_SPOUSE: (.*)/", $factrec, $match);
			if ($ct>0) {
				$spouse=WT_Person::getInstance($match[1]);
				if ($spouse) {
					echo "<a href=\"", $spouse->getHtmlUrl(), "\">";
					if ($spouse->canDisplayName()) {
						echo PrintReady($spouse->getFullName());
					} else {
						echo WT_I18N::translate('Private');
					}
					echo "</a>";
				}
				if (empty($SEARCH_SPIDER)) {
					$ct = preg_match("/WT_FAMILY_ID: (.*)/", $factrec, $match);
					if ($ct>0) {
						$famid = trim($match[1]);
						$family = WT_Family::getInstance($famid);
						if ($family) {
							if ($spouse) echo " - ";
							echo '<a href="', $family->getHtmlUrl(), '">', WT_I18N::translate('View Family'), '</a>';
						}
					}
				}
			}
			echo "<br />";
			print_fact_notes($row["m_gedrec"], $nlevel);
			print_fact_sources($row["m_gedrec"], $nlevel);
			echo "</td></tr></table>";
		}
		$objectNum ++;
	}
}
/**
 * print an address structure
 *
 * takes a gedcom ADDR structure and prints out a human readable version of it.
 * @param string $factrec The ADDR subrecord
 * @param int $level The gedcom line level of the main ADDR record
 */
function print_address_structure($factrec, $level) {
	global $POSTAL_CODE;

	//   $POSTAL_CODE = 'false' - before city, 'true' - after city and/or state
	//-- define per gedcom till can do per address countries in address languages
	//-- then this will be the default when country not recognized or does not exist
	//-- both Finland and Suomi are valid for Finland etc.
	//-- see http://www.bitboost.com/ref/international-address-formats.html

	$nlevel = $level+1;
	$ct = preg_match_all("/$level ADDR(.*)/", $factrec, $omatch, PREG_SET_ORDER);
	for ($i=0; $i<$ct; $i++) {
		$arec = get_sub_record($level, "$level ADDR", $factrec, $i+1);
		$resultText = "";
		if ($level>1) $resultText .= "<span class=\"label\">".WT_Gedcom_Tag::getLabel('ADDR').": </span><br /><div class=\"indent\">";
		$cn = preg_match("/$nlevel _NAME (.*)/", $arec, $cmatch);
		if ($cn>0) $resultText .= str_replace("/", "", $cmatch[1])."<br />";
		$resultText .= PrintReady(trim($omatch[$i][1]));
		$cont = get_cont($nlevel, $arec);
		if (!empty($cont)) $resultText .= str_replace(array(" ", "<br&nbsp;"), array("&nbsp;", "<br "), PrintReady($cont));
		else {
			if (strlen(trim($omatch[$i][1])) > 0) echo "<br />";
			$cs = preg_match("/$nlevel ADR1 (.*)/", $arec, $cmatch);
			if ($cs>0) {
				if ($cn==0) {
					$resultText .= "<br />";
					$cn=0;
				}
				$resultText .= PrintReady($cmatch[1]);
			}
			$cs = preg_match("/$nlevel ADR2 (.*)/", $arec, $cmatch);
			if ($cs>0) {
				if ($cn==0) {
					$resultText .= "<br />";
					$cn=0;
				}
				$resultText .= PrintReady($cmatch[1]);
			}

			if (!$POSTAL_CODE) {
				$cs = preg_match("/$nlevel POST (.*)/", $arec, $cmatch);
				if ($cs>0) {
					$resultText .= "<br />".PrintReady($cmatch[1]);
				}
				$cs = preg_match("/$nlevel CITY (.*)/", $arec, $cmatch);
				if ($cs>0) {
					$resultText .= " ".PrintReady($cmatch[1]);
				}
				$cs = preg_match("/$nlevel STAE (.*)/", $arec, $cmatch);
				if ($cs>0) {
					$resultText .= ", ".PrintReady($cmatch[1]);
				}
			}
			else {
				$cs = preg_match("/$nlevel CITY (.*)/", $arec, $cmatch);
				if ($cs>0) {
					$resultText .= "<br />".PrintReady($cmatch[1]);
				}
				$cs = preg_match("/$nlevel STAE (.*)/", $arec, $cmatch);
				if ($cs>0) {
					$resultText .= ", ".PrintReady($cmatch[1]);
				}
				$cs = preg_match("/$nlevel POST (.*)/", $arec, $cmatch);
				if ($cs>0) {
					$resultText .= " ".PrintReady($cmatch[1]);
				}
			}

			$cs = preg_match("/$nlevel CTRY (.*)/", $arec, $cmatch);
			if ($cs>0) {
				$resultText .= "<br />".PrintReady($cmatch[1]);
			}
		}
		if ($level>1) $resultText .= "</div>";
		// Here we can examine the resultant text and remove empty tags
		echo $resultText;
	}
	$resultText = "";
	$resultText .= "<table>";
	$ct = preg_match_all("/$level PHON (.*)/", $factrec, $omatch, PREG_SET_ORDER);
	if ($ct>0) {
		for ($i=0; $i<$ct; $i++) {
			$resultText .= "<tr>";
			$resultText .= "<td><span class=\"label\"><b>".WT_Gedcom_Tag::getLabel('PHON').": </b></span></td><td><span class=\"field\">";
			$resultText .= getLRM() . $omatch[$i][1] . getLRM();
			$resultText .= "</span></td></tr>";
		}
	}
	$ct = preg_match_all("/$level FAX (.*)/", $factrec, $omatch, PREG_SET_ORDER);
	if ($ct>0) {
		for ($i=0; $i<$ct; $i++) {
			$resultText .= "<tr>";
			$resultText .= "<td><span class=\"label\"><b>".WT_Gedcom_Tag::getLabel('FAX').": </b></span></td><td><span class=\"field\">";
			$resultText .= getLRM() . $omatch[$i][1] . getLRM();
			$resultText .= "</span></td></tr>";
		}
	}
	$ct = preg_match_all("/$level EMAIL (.*)/", $factrec, $omatch, PREG_SET_ORDER);
	if ($ct>0) {
		for ($i=0; $i<$ct; $i++) {
			$resultText .= "<tr>";
			$resultText .= "<td><span class=\"label\"><b>".WT_Gedcom_Tag::getLabel('EMAIL').": </b></span></td><td><span class=\"field\">";
			$resultText .= "<a href=\"mailto:".$omatch[$i][1]."\">".$omatch[$i][1]."</a>";
			$resultText .= "</span></td></tr>";
		}
	}
	$ct = preg_match_all("/$level (WWW|URL) (.*)/", $factrec, $omatch, PREG_SET_ORDER);
	if ($ct>0) {
		for ($i=0; $i<$ct; $i++) {
			$resultText .= "<tr>";
			$resultText .= "<td><span class=\"label\"><b>".WT_Gedcom_Tag::getLabel($omatch[$i][1]).": </b></span></td><td><span class=\"field\">";
			$resultText .= "<a href=\"".$omatch[$i][2]."\" target=\"_blank\">".$omatch[$i][2]."</a>";
			$resultText .= "</span></td></tr>";
		}
	}
	$resultText .= "</table>";
	if ($resultText!="<table></table>") echo $resultText;
}

function print_main_sources($factrec, $level, $pid, $linenum, $noedit=false) {
	global $WT_IMAGES;

	if (!canDisplayFact($pid, WT_GED_ID, $factrec)) {
		return;
	}

	$nlevel = $level+1;
	$styleadd="";
	if (strpos($factrec, "WT_NEW")!==false) $styleadd="change_new";
	if (strpos($factrec, "WT_OLD")!==false) $styleadd="change_old";
	// -- find source for each fact
	$ct = preg_match_all("/$level SOUR @(.*)@/", $factrec, $match, PREG_SET_ORDER);
	$spos2 = 0;
	for ($j=0; $j<$ct; $j++) {
		$sid = $match[$j][1];
		$spos1 = strpos($factrec, "$level SOUR @".$sid."@", $spos2);
		$spos2 = strpos($factrec, "\n$level", $spos1);
		if (!$spos2) $spos2 = strlen($factrec);
		$srec = substr($factrec, $spos1, $spos2-$spos1);
		if (WT_Source::getInstance($sid)->canDisplayDetails()) {
			if ($level==2) echo "<tr class=\"row_sour2\">";
			else echo "<tr>";
			echo "<td class=\"descriptionbox";
			if ($level==2) echo " rela";
			echo " $styleadd width20\">";
			$temp = preg_match("/^\d (\w*)/", $factrec, $factname);
			$factlines = explode("\n", $factrec); // 1 BIRT Y\n2 SOUR ...
			$factwords = explode(" ", $factlines[0]); // 1 BIRT Y
			$factname = $factwords[1]; // BIRT
			$parent=WT_GedcomRecord::getInstance($pid);
			if ($factname == "EVEN" || $factname=="FACT") {
				// Add ' EVEN' to provide sensible output for an event with an empty TYPE record
				$ct = preg_match("/2 TYPE (.*)/", $factrec, $ematch);
				if ($ct>0) {
					$factname = trim($ematch[1]);
					echo $factname;
				} else {
					echo WT_Gedcom_Tag::getLabel($factname, $parent);
				}
			} else
			if (!$noedit && WT_USER_CAN_EDIT && !FactEditRestricted($pid, $factrec) && $styleadd!="red") {
				echo "<a onclick=\"return edit_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Edit')."\">";
					if ($level==1) echo "<img class=\"icon\" src=\"", $WT_IMAGES["source"], "\" alt=\"\" />";
					echo WT_Gedcom_Tag::getLabel($factname, $parent). "</a>";
				echo "<div class=\"editfacts\">";
					echo "<a onclick=\"return edit_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Edit')."\"><span class=\"editlink\"><span class=\"link_text\">".WT_I18N::translate('Edit')."</span></span></a>";
					echo "<a onclick=\"return copy_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Copy')."\"><span class=\"copylink\"><span class=\"link_text\">".WT_I18N::translate('Copy')."</span></span></a>";
					echo "<a onclick=\"return delete_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Delete')."\"><span class=\"deletelink\"><span class=\"link_text\">".WT_I18N::translate('Delete')."</span></span></a>";
				echo "</div>";
			} else {
				echo WT_Gedcom_Tag::getLabel($factname, $parent);
			}
			echo "</td>";
			echo "<td class=\"optionbox $styleadd wrap\">";
			//echo "<td class=\"facts_value$styleadd\">";
			$source=WT_Source::getInstance($sid);
			if ($source) {
				echo "<a href=\"", $source->getHtmlUrl(), "\">", PrintReady($source->getFullName()), "</a>";
				// PUBL
				$text = get_gedcom_value("PUBL", "1", $source->getGedcomRecord());
				if (!empty($text)) {
					echo "<br /><span class=\"label\">", WT_Gedcom_Tag::getLabel('PUBL'), ": </span>";
					echo $text;
				}
				// 2 RESN tags.  Note, there can be more than one, such as "privacy" and "locked"
				if (preg_match_all("/\n2 RESN (.+)/", $factrec, $matches)) {
					foreach ($matches[1] as $match) {
						echo '<br/><span class="label">', WT_Gedcom_Tag::getLabel('RESN'), ':</span> <span class="field">';
						switch ($match) {
						case 'none':
							// Note: "2 RESN none" is not valid gedcom, and the GUI will not let you add it.
							// However, webtrees privacy rules will interpret it as "show an otherwise private fact to public".
							echo '<img src="images/RESN_none.gif" /> ', WT_I18N::translate('Show to visitors');
							break;
						case 'privacy':
							echo '<img src="images/RESN_privacy.gif" /> ', WT_I18N::translate('Show to members');
							break;
						case 'confidential':
							echo '<img src="images/RESN_confidential.gif" /> ', WT_I18N::translate('Show to managers');
							break;
						case 'locked':
							echo '<img src="images/RESN_locked.gif" /> ', WT_I18N::translate('Only managers can edit');
							break;
						default:
							echo $match;
							break;
						}
						echo '</span>';
					}
				}
				$cs = preg_match("/$nlevel EVEN (.*)/", $srec, $cmatch);
				if ($cs>0) {
					echo "<br /><span class=\"label\">", WT_Gedcom_Tag::getLabel('EVEN'), " </span><span class=\"field\">", $cmatch[1], "</span>";
					$cs = preg_match("/".($nlevel+1)." ROLE (.*)/", $srec, $cmatch);
					if ($cs>0) echo "<br />&nbsp;&nbsp;&nbsp;&nbsp;<span class=\"label\">", WT_Gedcom_Tag::getLabel('ROLE'), " </span><span class=\"field\">$cmatch[1]</span>";
				}
				if ($source) {
					echo printSourceStructure(getSourceStructure($srec));
					echo "<div class=\"indent\">";
					print_media_links($srec, $nlevel);
					if ($nlevel==2) {
						print_media_links($source->getGedcomRecord(), 1);
					}
					print_fact_notes($srec, $nlevel);
					if ($nlevel==2) {
						print_fact_notes($source->getGedcomRecord(), 1);
					}
					echo "</div>";
				}
			}
			echo "</td></tr>";
		}
	}
}

/**
 * Print SOUR structure
 *
 *  This function prints the input array of SOUR sub-records built by the
 *  getSourceStructure() function.
 */
function printSourceStructure($textSOUR) {
	$html='';

	if ($textSOUR['PAGE']) {
		$html.='<div class="indent"><span class="label">'.WT_Gedcom_Tag::getLabel('PAGE').':</span> <span class="field">'.PrintReady(expand_urls($textSOUR['PAGE'])).'</span></div>';
	}

	if ($textSOUR['EVEN']) {
		$html.='<div class="indent"><span class="label">'.WT_Gedcom_Tag::getLabel('EVEN').': </span><span class="field">'.PrintReady($textSOUR['EVEN']).'</span></div>';
		if ($textSOUR['ROLE']) {
			$html.='<div class="indent"><span class="label">'.WT_Gedcom_Tag::getLabel('ROLE').': </span><span class="field">'.PrintReady($textSOUR['ROLE']).'</span></div>';
		}
	}

	if ($textSOUR['DATE'] || count($textSOUR['TEXT'])) {
		if ($textSOUR['DATE']) {
			$date=new WT_Date($textSOUR['DATE']);
			$html.='<div class="indent"><span class="label">'.WT_Gedcom_Tag::getLabel('DATA:DATE').':</span> <span class="field">'.$date->Display(false).'</span></div>';
		}
		foreach ($textSOUR['TEXT'] as $text) {
			$html.='<div class="indent"><span class="label">'.WT_Gedcom_Tag::getLabel('TEXT').':</span> <span class="field">'.PrintReady(expand_urls($text)).'</span></div>';
		}
	}

	if ($textSOUR['QUAY']!='') {
		$html.='<div class="indent"><span class="label">'.WT_Gedcom_Tag::getLabel('QUAY').':</span> <span class="field">'.PrintReady($textSOUR['QUAY']).'</span></div>';
	}

	return $html;
}

/**
 * Extract SOUR structure from the incoming Source sub-record
 *
 * The output array is defined as follows:
 *  $textSOUR['PAGE'] = Source citation
 *  $textSOUR['EVEN'] = Event type
 *  $textSOUR['ROLE'] = Role in event
 *  $textSOUR['DATA'] = place holder (no text in this sub-record)
 *  $textSOUR['DATE'] = Entry recording date
 *  $textSOUR['TEXT'] = (array) Text from source
 *  $textSOUR['QUAY'] = Certainty assessment
 */
function getSourceStructure($srec) {
	// Set up the output array
	$textSOUR=array(
		'PAGE'=>'',
		'EVEN'=>'',
		'ROLE'=>'',
		'DATA'=>'',
		'DATE'=>'',
		'TEXT'=>array(),
		'QUAY'=>'',
	);

	if ($srec) {
		$subrecords=explode("\n", $srec);
		for ($i=0; $i<count($subrecords); $i++) {
			$level=substr($subrecords[$i], 0, 1);
			$tag  =substr($subrecords[$i], 2, 4);
			$text =substr($subrecords[$i], 7);
			$i++;
			for (; $i<count($subrecords); $i++) {
				$nextTag = substr($subrecords[$i], 2, 4);
				if ($nextTag!='CONT') {
					$i--;
					break;
				}
				if ($nextTag=='CONT') $text .= '<br />';
				$text .= rtrim(substr($subrecords[$i], 7));
			}
			if ($tag=='TEXT') {
				$textSOUR[$tag][] = $text;
			} else {
				$textSOUR[$tag] = $text;
			}
		}
	}

	return $textSOUR;
}

/**
 * print main note row
 *
 * this function will print a table row for a fact table for a level 1 note in the main record
 * @param string $factrec the raw gedcom sub record for this note
 * @param int $level The start level for this note, usually 1
 * @param string $pid The gedcom XREF id for the level 0 record that this note is a part of
 * @param int $linenum The line number in the level 0 record where this record was found.  This is used for online editing.
 * @param boolean $noedit Whether or not to allow this fact to be edited
 */
function print_main_notes($factrec, $level, $pid, $linenum, $noedit=false) {
	global $GEDCOM, $WT_IMAGES, $TEXT_DIRECTION;

	$ged_id=get_id_from_gedcom($GEDCOM);
	$styleadd="";
	if (strpos($factrec, "WT_NEW")!==false) $styleadd="change_new";
	if (strpos($factrec, "WT_OLD")!==false) $styleadd="change_old";
	$nlevel = $level+1;
	$ct = preg_match_all("/$level NOTE(.*)/", $factrec, $match, PREG_SET_ORDER);
	for ($j=0; $j<$ct; $j++) {
		$nrec = get_sub_record($level, "$level NOTE", $factrec, $j+1);
		if (!canDisplayFact($pid, $ged_id, $factrec)) return false;
		$nt = preg_match("/\d NOTE @(.*)@/", $match[$j][0], $nmatch);
		if ($nt>0) {
			$nid = $nmatch[1];
			if (empty($styleadd) && find_updated_record($nid, WT_GED_ID)!==null) {
				$styleadd = "change_old";
				$newfactrec = $factrec.="\nWT_NEW";
				print_main_notes($factrec, $level, $pid, $linenum, $noedit);
			}
		}
		if ($level>=2) echo "<tr class=\"row_note2\">";
		else echo "<tr>";
		echo "<td valign=\"top\" class=\"descriptionbox";
		if ($level>=2) echo " rela";
		echo " $styleadd width20\">";
		if (!$noedit && WT_USER_CAN_EDIT && !FactEditRestricted($pid, $factrec) && $styleadd!="change_old") {
			echo "<a onclick=\"return edit_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Edit')."\">";
			if ($level<2) {
				echo "<img class=\"icon\" src=\"", $WT_IMAGES["note"], "\" alt=\"\" />";
				if (strstr($factrec, "1 NOTE @" )) {
					echo WT_Gedcom_Tag::getLabel('SHARED_NOTE');
				} else {
					echo WT_Gedcom_Tag::getLabel('NOTE');
				}
				echo "</a>";
				echo "<div class=\"editfacts\">";
				echo "<a onclick=\"return edit_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Edit')."\"><span class=\"editlink\"><span class=\"link_text\">".WT_I18N::translate('Edit')."</span></span></a>";
				echo "<a onclick=\"return copy_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Copy')."\"><span class=\"copylink\"><span class=\"link_text\">".WT_I18N::translate('Copy')."</span></span></a>";
				echo "<a onclick=\"return delete_record('$pid', $linenum);\" href=\"javascript:;\" title=\"".WT_I18N::translate('Delete')."\"><span class=\"deletelink\"><span class=\"link_text\">".WT_I18N::translate('Delete')."</span></span></a>";
				echo "</div>";
			}
		} else {
			if ($level<2) {
				echo "<img class=\"icon\" src=\"", $WT_IMAGES["note"], "\" alt=\"\" />";
				if (strstr($factrec, "1 NOTE @" )) {
					echo WT_Gedcom_Tag::getLabel('SHARED_NOTE');
				} else {
					echo WT_Gedcom_Tag::getLabel('NOTE');
				}
			}
			$factlines = explode("\n", $factrec); // 1 BIRT Y\n2 NOTE ...
			$factwords = explode(" ", $factlines[0]); // 1 BIRT Y
			$factname = $factwords[1]; // BIRT
			$parent=WT_GedcomRecord::getInstance($pid);
			if ($factname == "EVEN" || $factname=="FACT") {
				// Add ' EVEN' to provide sensible output for an event with an empty TYPE record
				$ct = preg_match("/2 TYPE (.*)/", $factrec, $ematch);
				if ($ct>0) {
					$factname = trim($ematch[1]);
					echo $factname;
				} else {
					echo WT_Gedcom_Tag::getLabel($factname, $parent);
				}
			} else if ($factname != "NOTE") {
				// Note is already printed
				echo WT_Gedcom_Tag::getLabel($factname, $parent);
			}
		}
		echo "</td>";
			if ($nt==0) {
				//-- print embedded note records
				$text = preg_replace("/~~/", "<br />", trim($match[$j][1]));
				$text .= get_cont($nlevel, $nrec);
				$text = expand_urls($text);
				$text = PrintReady($text);
			} else {
				//-- print linked/shared note records
				$note=WT_Note::getInstance($nid);
				if ($note) {
					$noterec=$note->getGedcomRecord();				
					$nt = preg_match("/^0 @[^@]+@ NOTE (.*)/", $noterec, $n1match);
					$text = "";
					$centitl = "";
					if ($nt>0) {
						// If Census assistant installed, enable hotspot link on shared note title ---------------------
						if (file_exists(WT_ROOT.WT_MODULES_DIR.'GEDFact_assistant/_CENS/census_note_decode.php')) {
							$centitl  = str_replace("~~", "", trim($n1match[1]));
							$centitl  = str_replace("<br />", "", $centitl);
							$centitl  = "<a href=\"note.php?nid=$nid\">".$centitl."</a>";
						} else {
							$text = preg_replace("/~~/", "<br />", trim($n1match[1]));
						}
					}
					$text .= get_cont(1, $noterec);
					$text = expand_urls($text);
					$text = PrintReady($text)." <br />";
					// If Census assistant installed, and if Formatted Shared Note (using pipe "|" as delimiter) -------
					if (strstr($text, "|") && file_exists(WT_ROOT.WT_MODULES_DIR.'GEDFact_assistant/_CENS/census_note_decode.php')) {
						require WT_ROOT.WT_MODULES_DIR.'GEDFact_assistant/_CENS/census_note_decode.php';
					} else {
						$text = $centitl."".$text;
					}
				}
			}

			$align = "";
			if (!empty($text)) {
				if ($TEXT_DIRECTION=="rtl" && !hasRTLText($text) && hasLTRText($text)) $align=" align=\"left\"";
				if ($TEXT_DIRECTION=="ltr" && !hasLTRText($text) && hasRTLText($text)) $align=" align=\"right\"";
			}
		echo "<td class=\"optionbox $styleadd wrap\" $align>";
		if (!empty($text)) {
			echo $text;
			if (!empty($noterec)) print_fact_sources($noterec, 1);

			// 2 RESN tags.  Note, there can be more than one, such as "privacy" and "locked"
			if (preg_match_all("/\n2 RESN (.+)/", $factrec, $matches)) {
				foreach ($matches[1] as $match) {
					echo '<br/><span class="label">', WT_Gedcom_Tag::getLabel('RESN'), ':</span> <span class="field">';
					switch ($match) {
					case 'none':
						// Note: "2 RESN none" is not valid gedcom, and the GUI will not let you add it.
						// However, webtrees privacy rules will interpret it as "show an otherwise private fact to public".
						echo '<img src="images/RESN_none.gif" /> ', WT_I18N::translate('Show to visitors');
						break;
					case 'privacy':
						echo '<img src="images/RESN_privacy.gif" /> ', WT_I18N::translate('Show to members');
						break;
					case 'confidential':
						echo '<img src="images/RESN_confidential.gif" /> ', WT_I18N::translate('Show to managers');
						break;
					case 'locked':
						echo '<img src="images/RESN_locked.gif" /> ', WT_I18N::translate('Only managers can edit');
						break;
					default:
						echo $match;
						break;
					}
					echo '</span>';
				}
			}

			echo "<br />";
			print_fact_sources($nrec, $nlevel);
		}
		echo "</td></tr>";
	}
}

/**
 * Print the links to multi-media objects
 * @param string $pid The the xref id of the object to find media records related to
 * @param int $level The level of media object to find
 * @param boolean $related Whether or not to grab media from related records
 */
function print_main_media($pid, $level=1, $related=false, $noedit=false) {
	global $GEDCOM, $MEDIATYPE;
	$ged_id=get_id_from_gedcom($GEDCOM);

	$gedrec = find_gedcom_record($pid, $ged_id, true);
	$ids = array($pid);

	//-- find all of the related ids
	if ($related) {
		$ct = preg_match_all("/1 FAMS @(.*)@/", $gedrec, $match, PREG_SET_ORDER);
		for ($i=0; $i<$ct; $i++) {
			$ids[] = trim($match[$i][1]);
		}
	}

	//LBox -- if  exists, get a list of the sorted current objects in the indi gedcom record  -  (1 _WT_OBJE_SORT @xxx@ .... etc) ----------
	$sort_current_objes = array();
	if ($level>0) $sort_regexp = "/".$level." _WT_OBJE_SORT @(.*)@/";
	else $sort_regexp = "/_WT_OBJE_SORT @(.*)@/";
	$sort_ct = preg_match_all($sort_regexp, $gedrec, $sort_match, PREG_SET_ORDER);
	for ($i=0; $i<$sort_ct; $i++) {
		if (!isset($sort_current_objes[$sort_match[$i][1]])) $sort_current_objes[$sort_match[$i][1]] = 1;
		else $sort_current_objes[$sort_match[$i][1]]++;
		$sort_obje_links[$sort_match[$i][1]][] = $sort_match[$i][0];
	}
	// -----------------------------------------------------------------------------------------------

	// create ORDER BY list from Gedcom sorted records list  ---------------------------
	$orderbylist = 'ORDER BY '; // initialize
	foreach ($sort_match as $id) {
		$orderbylist .= "m_media='$id[1]' DESC, ";
	}
	$orderbylist = rtrim($orderbylist, ', ');
	// -----------------------------------------------------------------------------------------------

	//-- get a list of the current objects in the record
	$current_objes = array();
	if ($level>0) $regexp = "/".$level." OBJE @(.*)@/";
	else $regexp = "/OBJE @(.*)@/";
	$ct = preg_match_all($regexp, $gedrec, $match, PREG_SET_ORDER);
	for ($i=0; $i<$ct; $i++) {
		$match[$i][1]=strtoupper($match[$i][1]); // Force PHP to copy MySQL's case-insensitivity
		if (!isset($current_objes[$match[$i][1]])) $current_objes[$match[$i][1]] = 1;
		else $current_objes[$match[$i][1]]++;
		$obje_links[$match[$i][1]][] = $match[$i][0];
	}

	$media_found = false;
	$sqlmm = "SELECT ";
	$sqlmm .= "m_media, m_ext, m_file, m_titl, m_gedfile, m_gedrec, mm_gid, mm_gedrec FROM `##media`, `##media_mapping` where ";
	$sqlmm .= "mm_gid IN (";
	$vars=array();
	$i=0;
	foreach ($ids as $key=>$id) {
		if ($i>0) $sqlmm .= ", ";
		$sqlmm .= "?";
		$vars[]=$id;
		$i++;
	}
	$sqlmm .= ") AND mm_gedfile=? AND mm_media=m_media AND mm_gedfile=m_gedfile ";
	$vars[]=WT_GED_ID;
	//-- for family and source page only show level 1 obje references
	if ($level>0) {
		$sqlmm .= "AND mm_gedrec LIKE ?";
		$vars[]="{$level} OBJE%";
	}

	// LBox --- media sort -------------------------------------
	if ($sort_ct>0) {
		$sqlmm .= $orderbylist;
	} else {
		$sqlmm .= " ORDER BY mm_gid DESC ";
	}
	// ---------------------------------------------------------------

	$rows=WT_DB::prepare($sqlmm)->execute($vars)->fetchAll(PDO::FETCH_ASSOC);

	$foundObjs = array();
	foreach ($rows as $rowm) {
		$rowm['m_media']=strtoupper($rowm['m_media']); // Force PHP to copy MySQL's case-insensitivity
		if (isset($foundObjs[$rowm['m_media']])) {
			if (isset($current_objes[$rowm['m_media']])) $current_objes[$rowm['m_media']]--;
			continue;
		}
		$rows=array();

		//-- if there is a change to this media item then get the
		//-- updated media item and show it
		if ($newrec=find_updated_record($rowm["m_media"], $ged_id)) {
			$row = array();
			$row['m_media'] = $rowm["m_media"];
			$row['m_gedfile']=$rowm["m_gedfile"];
			$row['m_file'] = get_gedcom_value("FILE", 1, $newrec);
			$row['m_titl'] = get_gedcom_value("TITL", 1, $newrec);
			if (empty($row['m_titl'])) $row['m_titl'] = get_gedcom_value("FILE:TITL", 1, $newrec);
			$row['m_gedrec'] = $newrec;
			$et = preg_match("/(\.\w+)$/", $row['m_file'], $ematch);
			$ext = "";
			if ($et>0) $ext = substr(trim($ematch[1]), 1);
			$row['m_ext'] = $ext;
			$row['mm_gid'] = $pid;
			$row['mm_gedrec'] = $rowm["mm_gedrec"];
			$rows['new'] = $row;
			$rows['old'] = $rowm;
			// $current_objes[$rowm['m_media']]--;
		} else {
			if (!isset($current_objes[$rowm['m_media']]) && ($rowm['mm_gid']==$pid)) {
				$rows['old'] = $rowm;
			} else {
				$rows['normal'] = $rowm;
				if (isset($current_objes[$rowm['m_media']])) {
					$current_objes[$rowm['m_media']]--;
				}
			}
		}
		foreach ($rows as $rtype => $rowm) {
			$res = print_main_media_row($rtype, $rowm, $pid);
			$media_found = $media_found || $res;
			$foundObjs[$rowm['m_media']]=true;
		}
		$media_found = true;
	}

	//-- objects are removed from the $current_objes list as they are printed
	//-- any objects left in the list are new objects recently added to the gedcom
	//-- but not yet accepted into the database.  We will print them too.
	foreach ($current_objes as $media_id=>$value) {
		while ($value>0) {
			$objSubrec = array_pop($obje_links[$media_id]);
			$row = array();
			$newrec = find_gedcom_record($media_id, $ged_id, true);
			$row['m_media'] = $media_id;
			$row['m_gedfile']=$ged_id;
			$row['m_file'] = get_gedcom_value("FILE", 1, $newrec);
			$row['m_titl'] = get_gedcom_value("TITL", 1, $newrec);
			if (empty($row['m_titl'])) $row['m_titl'] = get_gedcom_value("FILE:TITL", 1, $newrec);
			$row['m_gedrec'] = $newrec;
			$et = preg_match("/(\.\w+)$/", $row['m_file'], $ematch);
			$ext = "";
			if ($et>0) $ext = substr(trim($ematch[1]), 1);
			$row['m_ext'] = $ext;
			$row['mm_gid'] = $pid;
			$row['mm_gedrec'] = get_sub_record($objSubrec{0}, $objSubrec, $gedrec);
			$res = print_main_media_row('new', $row, $pid);
			$media_found = $media_found || $res;
			$value--;
		}
	}
	if ($media_found) return true;
	else return false;
}

/**
 * print a media row in a table
 * @param string $rtype whether this is a 'new', 'old', or 'normal' media row... this is used to determine if the rows should be printed with an outline color
 * @param array $rowm An array with the details about this media item
 * @param string $pid The record id this media item was attached to
 */
function print_main_media_row($rtype, $rowm, $pid) {
	global $WT_IMAGES, $TEXT_DIRECTION, $GEDCOM, $THUMBNAIL_WIDTH, $USE_MEDIA_VIEWER, $SEARCH_SPIDER;

	$mediaobject = WT_Media::getInstance($rowm['m_media']);
	if (!$mediaobject || !$mediaobject->canDisplayDetails()) {
		return false;
	}

	$styleadd="";
	if ($rtype=='new') $styleadd = "change_new";
	if ($rtype=='old') $styleadd = "change_old";

	$linenum = 0;
	echo "<tr><td class=\"descriptionbox $styleadd width20\">";
	if ($rowm['mm_gid']==$pid && WT_USER_CAN_EDIT && (!FactEditRestricted($rowm['m_media'], $rowm['m_gedrec'])) && ($styleadd!="change_old")) {
		echo "<a onclick=\"return window.open('addmedia.php?action=editmedia&pid={$rowm['m_media']}&linktoid={$rowm['mm_gid']}', '_blank', 'top=50, left=50, width=600, height=500, resizable=1, scrollbars=1');\" href=\"javascript:;\" title=\"".WT_I18N::translate('Edit')."\">";
		echo "<img class=\"icon\" src=\"", $WT_IMAGES["media"], "\" alt=\"\" />". WT_Gedcom_Tag::getLabel('OBJE'). "</a>";
		echo "<div class=\"editfacts\">";
		echo "<a onclick=\"return window.open('addmedia.php?action=editmedia&pid={$rowm['m_media']}&linktoid={$rowm['mm_gid']}', '_blank', 'top=50, left=50, width=600, height=500, resizable=1, scrollbars=1');\" href=\"javascript:;\" title=\"".WT_I18N::translate('Edit')."\"><span class=\"editlink\"><span class=\"link_text\">".WT_I18N::translate('Edit')."</span></span></a>";
		echo "<a onclick=\"return copy_record('".$rowm['m_media']."', 'media');\" href=\"javascript:;\" title=\"".WT_I18N::translate('Copy')."\"><span class=\"copylink\"><span class=\"link_text\">".WT_I18N::translate('Copy')."</span></span></a>";
		echo "<a onclick=\"return delete_record('$pid', 'OBJE', '".$rowm['m_media']."');\" href=\"javascript:;\" title=\"".WT_I18N::translate('Delete')."\"><span class=\"deletelink\"><span class=\"link_text\">".WT_I18N::translate('Delete')."</span></span></a>";
		echo "</div>";
		echo "</td>";
	}

	// NOTE Print the title of the media
	echo "<td class=\"optionbox wrap $styleadd\"><span class=\"field\">";
	echo $mediaobject->displayMedia();
	$name = $mediaobject->getFullName();
	if (empty($SEARCH_SPIDER)) {
		echo '<a href="'.$mediaobject->getHtmlUrl().'">';
	}
	if ($TEXT_DIRECTION=="rtl" && !hasRTLText($name)) {
		echo "<em>", getLRM(), PrintReady(htmlspecialchars($name));
	} else {
		echo "<em>", PrintReady(htmlspecialchars($name));
	}
	$addtitle = get_gedcom_value("TITL:_HEB", 2, $rowm["m_gedrec"]);
	if (empty($addtitle)) $addtitle = get_gedcom_value("TITL:_HEB", 1, $rowm["m_gedrec"]);
	if (!empty($addtitle)) echo "<br />", PrintReady(htmlspecialchars($addtitle));
	$addtitle = get_gedcom_value("TITL:ROMN", 2, $rowm["m_gedrec"]);
	if (empty($addtitle)) $addtitle = get_gedcom_value("TITL:ROMN", 1, $rowm["m_gedrec"]);
	if (!empty($addtitle)) echo "<br />", PrintReady(htmlspecialchars($addtitle));
	echo "</em>";
	if (empty($SEARCH_SPIDER)) {
		echo "</a>";
	}

	$imgsize = $mediaobject->getImageAttributes('main');
	if (!empty($imgsize['ext'])) {
		// this does not match the output of the media controller exactly
		echo "<br /><span class=\"label\">", WT_Gedcom_Tag::getLabel('FORM'), ": </span> <span class=\"field\">", $imgsize['ext'], "</span>";
	}
	if (!empty($imgsize['WxH'])) {
		echo "<span class=\"label\"><br />", WT_I18N::translate('Image Dimensions'), ": </span> <span class=\"field\" dir=\"ltr\">", $imgsize['WxH'], "</span>";
	}
	if ($mediaobject->getFilesizeraw()>0) {
		echo "<span class=\"label\"><br />", WT_I18N::translate('File Size'), ": </span> <span class=\"field\" dir=\"ltr\">", $mediaobject->getFilesize() , "</span>";
	}
	if (preg_match('/2 DATE (.+)/', get_sub_record("FILE", 1, $rowm["m_gedrec"]), $match)) {
		$media_date=new WT_Date($match[1]);
		$md = $media_date->Display(true);
		echo "<br /><span class=\"label\">", WT_Gedcom_Tag::getLabel('DATE'), ": </span> ", $md;
	}
	$ttype = preg_match("/\d TYPE (.*)/", $rowm["m_gedrec"], $match);
	if ($ttype>0) {
		$mediaType = WT_Gedcom_Tag::getFileFormTypeValue($match[1]);
		echo "<br /><span class=\"label\">", WT_I18N::translate('Type'), ": </span> <span class=\"field\">$mediaType</span>";
	}
	echo "</span>";
	echo "<br />";
	//-- print spouse name for marriage events
	if ($rowm['mm_gid']!=$pid) {
		$person=WT_Person::getInstance($pid);
		$family=WT_Family::getInstance($rowm['mm_gid']);
		if ($family) {
			$spouse=$family->getSpouse($person);
			if ($spouse) {
				echo '<a href="', $spouse->getHtmlUrl(), '">', $spouse->getFullName(), '</a> - ';
			}
			echo '<a href="', $family->getHtmlUrl(), '">', WT_I18N::translate('View Family'), '</a><br />';
		}
	}
	//-- don't show _PRIM option to regular users
	if (WT_USER_GEDCOM_ADMIN) {
		$prim = get_gedcom_value("_PRIM", 1, $rowm["m_gedrec"]);
		if (!empty($prim)) {
			echo "<span class=\"label\">", WT_Gedcom_Tag::getLabel('_PRIM'), ":</span> ";
			if ($prim=="Y") echo WT_I18N::translate('Yes'); else echo WT_I18N::translate('No');
			echo "<br />";
		}
	}
	//-- don't show _THUM option to regular users
	if (WT_USER_GEDCOM_ADMIN) {
		$thum = get_gedcom_value("_THUM", 1, $rowm["m_gedrec"]);
		if (!empty($thum)) {
			echo "<span class=\"label\">", WT_Gedcom_Tag::getLabel('_THUM'), ":</span> ";
			if ($thum=="Y") echo WT_I18N::translate('Yes'); else echo WT_I18N::translate('No');
			echo "<br />";
		}
	}
	print_fact_notes($rowm["m_gedrec"], 1);
	print_fact_sources($rowm["m_gedrec"], 1);
	echo "</td></tr>";

	// print_r($rowm);
	// print_r($mediaobject);

	return true;
}

// -----------------------------------------------------------------------------
//  Extra print_facts_functions for lightbox and reorder media
// -----------------------------------------------------------------------------

if (WT_USE_LIGHTBOX) {
	require_once WT_ROOT.WT_MODULES_DIR.'lightbox/functions/lightbox_print_media.php';
	require_once WT_ROOT.WT_MODULES_DIR.'lightbox/functions/lightbox_print_media_row.php';
}

require_once WT_ROOT.'includes/functions/functions_media_reorder.php';

// -----------------------------------------------------------------------------
//  End extra print_facts_functions for lightbox and reorder media
// -----------------------------------------------------------------------------
