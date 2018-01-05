<?
// (c) Glo, 2006-2009
/*
foreach ($dirs_all as $dir)
{	chdir("$root/$dir");
	выбор топовой картинки

gal groups -> $grs_table

foreach groups (includes all)
morda_<group>_0,1,2,3

	// выбор 2-x топовых картинок в базе либо по rnd из галеры
	// если нет - взять по rnd
	// если нет тумбы в thumbs - сделать, скопировать в topthumbs (вторую склепать) под соотв индексом
*/
Error_Reporting(E_ALL);
@ ignore_user_abort(1);
@ ini_set(max_execution_time, 0);
#@ ini_set(memory_limit, '64M');
@ set_time_limit(0);


include "gals_header.php";
if (!$dbh) exit;
$req="SET NAMES cp1251"; myreq();
setlocale(LC_ALL, "ru_RU.CP1251"); // work
//setlocale(LC_ALL, "ru_RU"); // work

	$forbidden_words=urldecode("%F5%F3%E9%7C%F5%F3%E5%7C%F5%F3%B8%7C%E5%E1%F3%7C%E5%E1%B8%7C%E5%E1%E5%7C%E5%E1%E0%7C%E5%E1%EB%7C%EF%E8%E7%E4%7C%D5%D3%C9%7C%D5%D3%C5%7C%D5%D3%A8%7C%C5%C1%D3%7C%C5%C1%A8%7C%C5%C1%C5%7C%C5%C1%C0%7C%C5%C1%CB%7C%CF%C8%C7%C4");

include "gals_cfg.php";
$file_opt="$root/gals_mk_morda_opt";
//// site check

$outquant=10;	// в секундых
$t=time(); $ttmp=$t;
// статус скрипта  <last update>;<offset>;    offset<->galid
if (file_exists($file_opt))
{	list($last_update, $last_add_reserve)=explode(';',file_get_contents($file_opt));
}
else{ $last_update=0; $last_add_reserve=0; }
if ($t < ($last_update+$outquant*2))
{	echo "Already run"; exit;
}
if ($t >= ($last_add_reserve+str2time($add_reserve_period)))
{	$last_add_reserve=$t;
	$flag_add_reserve=1;
}
//if ($_SERVER["QUERY_STRING"]=='refresh'){ $last_add_reserve=0; $fresh_flag=1; }
// функция обновления статуса
function statusupdate($fl='')
{	$t=time(); $upd_fl=0;
	global $last_update, $last_add_reserve, $outquant, $file_opt;
	if ($t > ($last_update+$outquant))
	{	$upd_fl=1;
		$last_update=$t;
		$fh=fopen($file_opt,'w'); 
		fwrite($fh,"$t;$last_add_reserve");
		fclose($fh);
	}
	if ($fl=='end')
	{// reset $last_update
		$fh=fopen($file_opt,'w'); 
		fwrite($fh,"0;$last_add_reserve");
		fclose($fh);
	}
	return $upd_fl;
}
statusupdate();


$before1=' ';
$before2=' 
';
$after='

';

$_SERVER["SERVER_NAME"]=ereg_replace ("www\.", "", trim($_SERVER["SERVER_NAME"]));
srand((float)microtime()*10000000);

// $lang
if (isset($lang)){ if ($lang!='rus') $lang='eng';}
else $lang='eng';
// правка $lang
if ($lang=='rus') $photos=' '.$picsrus;
else $photos=' '.$picseng;

// показа галер в родительском окне ( НЕ target=_blank )
if (isset($gals_no_new_window)) $target_view="";
else $target_view="target='_blank'";

// таг картинки при имени группы
if (!isset($gr_imge_tag)) $gr_imge_tag='';

// огранич-е ко-ва галер для opthumbs
if (!isset($topthlim)) $topthlim=100;

// огранич-е ко-ва топтумб на галеру
if (!isset($topthpergal)) $topthpergal=2;


chdir("$root");
$remake=0;


{	
	if (!file_exists("$top_thumbs")) mkdir("$top_thumbs",0755);
	$cur_day=time()/(3600*24);
	$fhtp=fopen("$top_thumbs/top_thumbs.csv.new","w+");
	$fhtp1=fopen("$top_thumbs/ttfrs.csv.new","w+");
	$fhtp2=fopen("$top_thumbs/top_thumbs1.csv.new","w+");
	if (!file_exists("$top_thumbs/tmp")) mkdir("$top_thumbs/tmp",0755);
	$gals_table=$DBprefix.ereg_replace ("[`'-.()!?#=><,]", "_", $_SERVER["SERVER_NAME"]);
	$pics_table=$gals_table."_pics";
	$grs_table=$gals_table."_grs";

	// добавление случайной галеры из резерва
	if (isset($flag_add_reserve))
	{	$req="SELECT id,gal FROM ".$gals_table." WHERE enabled='1' AND reserve='1' AND pics>0 ORDER BY RAND() LIMIT 1"; myreq();
		if ($sth) @$row=mysql_fetch_row($sth);
		echo "*** add gal $row[1] from reserve<br>";
		if (isset($row[1]))
		{	$id=$row[0]; $gal=$row[1];
			$req="UPDATE ".$gals_table." SET reserve='0',date='$t',ready='1' WHERE id='$id'"; myreq();
			// обновляем файл даты галеры
			$fh=fopen("$gals_dir/$gal/date.date",'w'); fclose($fh);
			unset($id,$gal,$fh);
		}
	}

	
	$grlist=''; $groups=array();
	$grlist_karta_a=array();

	// список активных групп
	$req="SELECT groups FROM ".$gals_table." WHERE ready='1' ORDER BY site,gal"; myreq();
	if ($sth)
	{	$groups_real=array();
		while (@$row=mysql_fetch_row($sth))
		{	$gr_tmp=explode(",",$row[0]);		
		    if (strlen(trim($gr_tmp[0]))>0) $groups_real=array_merge($groups_real,$gr_tmp);
		}
		$groups_real=array_unique($groups_real);
	}
	// чтение списка групп $groups
	//			 0				1				2
	$req="SELECT name,".$lang."title,".$lang."name FROM $grs_table ORDER BY ".$lang."name"; myreq();
//////echo $req; statusupdate('end');exit; 
	if ($sth) while (@$row=mysql_fetch_row($sth))
	{	// чтение параметров действующих групп
		if (strlen(array_search($row[0],$groups_real))!=0)
		{	// заполнение group titles array
			$groups[]=$row[0]; 
			if ($row[1]=='') $row[1]=$row[0];
			if ($row[2]=='') $row[2]=$row[0];
			$grtitles[$row[0]]=$row[1];
			if ($row[0]!='new')		// new - граппа для еще не отсортированных
			{	// пополнение СПИСКА ГРУПП (HTML блока с ссылками на рейтинги групп)
                // читаем имя галеры данной группы
				$topgal_sth='';
				$req="SELECT gal FROM ".$gals_table." WHERE ready='1' AND groups LIKE '%".$row[0]."%' ORDER BY reqs DESC"; myreqp($topgal_sth);
				// количество галер в данной группе
				if ($topgal_sth) @$gals_in_group=mysql_numrows($topgal_sth);
			    if (!isset($min_gals_for_group)) $min_gals_for_group=4;

    			if ($gals_in_group < $min_gals_for_group)
				{	// читаем имя первой галеры данной группы
    				if (@$topgal_row=mysql_fetch_row($topgal_sth))
					{	$grlist.="$gr_imge_tag<a href='/$gals_dir/".(urlencode($topgal_row[0]))."/glocind.php?no=1' class='stopka'>".$row[2]."</a><br> ";
						$grlist_karta_a[]="$gr_imge_tag<a href='/$gals_dir/".(urlencode($topgal_row[0]))."/glocind.php?no=1' class='stopka'>".$row[2]."</a>";
					}
				}
				else 
				{	// в группе достаточно галер
					$grlist.="$gr_imge_tag<a href='".$pgname.'_'.$row[0]."_0.html' class='stopka'>".$row[2]."</a><br> ";
					$grlist_karta_a[]= "$gr_imge_tag<div class='thumb'><a href='".$pgname.'_'.$row[0]."_0.html'  target='_blank'><img src='/topthumbs/".$row[0]."-1-1.jpg' ><br>".$row[2]."</a></div>";
				}
/*
<a class="stopka" href="/gals/pelagian/glocind.php?no=1">морские жители</a>
<br/>
	ERROR HERE:
<a class="stopka" href="/gals/piece/glocind.php?no=1"/>
<br/>
<a class="stopka" href="/gals/prodigiesofnature/glocind.php?no=1">чудеса природа</a>
*/
			}
		}
	}
//echo $req.$grlist; statusupdate('end');exit; 
	if ($grlist!='')
	{	$grlist=substr($grlist,0,-5); // kill '<br> ' в конце
//		if ($lang!='rus') $grlist="<a href='$pgname.html' class='stopka'>ALL CATGORIES</a><br> $grlist";
//		else $grlist="<a href='$pgname.html' class='stopka'>ВСЕ КАТЕГОРИИ</a><br> $grlist";

/*$gr_map_rows=10;
$gr_map_cols=5;
$gr_map_filename="map_gr.html";*/
        $not_end=1;
        $gr_map="";
//$gr_map= $gr_map.implode("|",$grlist_karta_a)."<table>";
		// генерация карты групп
		while ($not_end)
		{	
			$rows=0;
			while ($not_end && $rows<$gr_map_rows)
			{	$gr_map= $gr_map."\n";
			    $cols=0;
				while ($not_end && $cols<$gr_map_cols)
				{	$gr_map= $gr_map."<div class='thumb'>";
					if ($grlist_item=each($grlist_karta_a))
					{	$gr_map= $gr_map.($grlist_item['value'])."<br>";
					}
					else $not_end=0;
					$gr_map= $gr_map."</div>";
					$cols++;
 				}
				$gr_map= $gr_map."\n";
				$rows++;
			}
			$gr_map= $gr_map."<hr>\n";
		}
		
        $gr_map=$gr_map."";

		$gr_map_template= file_get_contents($gr_map_template_file);
		$gr_map_pgout=preg_replace("|$gr_map_sample|",$gr_map,$gr_map_template);
		
		$fh=fopen($gr_map_filename, "w+");
		fputs($fh, $gr_map_pgout);
		fclose($fh);
	}
	array_unshift($groups,'all');

	// ГРУППЫ
	foreach ($groups as $pggroup)	
	{	if ($pggroup=='all'){ $mysqlmod=''; $grtpadd=''; $grpgnadd=''; $title="$deftitle"; } // $grtpadd добавляется к названию топовых тумб; $grpgnadd добавка между $pgname и номером page
		else
		{	/*if ($pggroup==$grtitles[$pggroup]) $title="$deftitle";
			else $title='';*/
				$title="";
			$mysqlmod="AND groups LIKE '%$pggroup%'"; 
			$grtpadd="$pggroup-"; $grpgnadd="_$pggroup"; 
			if (strlen($grtitles[$pggroup])==0) $grtitles[$pggroup]=$pggroup; // title = group
//			$title=$grtitles[$pggroup];
		}
echo "<hr>*** ".$pggroup." | ".sizeof($groups)."<br>";
		// чтение списка локальных папок по рейтингу или rnd
		$sthdir=''; $di=1;
		//				0   1     2           3      4
		//$req="(SELECT dir,title,description,date,groups FROM ".$gals_table." WHERE (($cur_day-cr_day)<$newgaldays && cr_day IS NOT NULL) AND (site='' OR site='".$_SERVER["SERVER_NAME"]."') AND enabled='1' $mysqlmod ORDER BY reqs DESC LIMIT 1000000)
		//				0			1				2			3		4	5		6	7	8
		$req="(SELECT gal,".$lang."title,".$lang."description,date,groups,site,thumb,id,pics FROM ".$gals_table." WHERE date>='".($t-str2time($new_fix_delay))."' AND ready='1' $mysqlmod ORDER BY reqs DESC LIMIT 1000000)
		UNION
		(SELECT gal,".$lang."title,".$lang."description,date,groups,site,thumb,id,pics FROM ".$gals_table." WHERE date<'".($t-str2time($new_fix_delay))."' AND ready='1' $mysqlmod ORDER BY reqs DESC LIMIT 1000000)"; myreqp($sthdir);
//echo $req;
//echo $req."<br>";
		if ($sthdir) @$dirstotal=mysql_numrows($sthdir);
//if ($dirstotal>4) $dirstotal=4;
        $pcnt=0; // признак первой страницы
		if ($dirstotal>0)
		{
			// ГАЛЕРЫ:
			for($gcnt=0; $gcnt<$dirstotal; $gcnt++)
			{	@$rowdir=mysql_fetch_row($sthdir);
//			    if ($rowdir[0]=='' && $rowdir[5]=='') break;
			    if ($rowdir[0]=='' && $rowdir[5]=='') echo "!!!ERROR!!! |$row[0]|$row[1]|$row[2]|$row[3]|$row[4]|$row[5]|$row[6]|$row[7]|<br>";
				$dir=$rowdir[0]; $galtitle=$rowdir[1]; $galdescr=$rowdir[2]; $date=$rowdir[3]; $site=$rowdir[5]; $thumb=$rowdir[6]; $galid=$rowdir[7]; $pics=$rowdir[8];
//if ($dir=="lesbian12") continue;
				// группа галеры для первой страницы all (иначе '')
				if ($pggroup=='all')
				{	$galgroups=array();
					$galgroups=explode(",",$rowdir[4]); $galgroup=$galgroups[0];
					unset($galgroups);
				}
				else $galgroup='';
//echo "$dir / ".($cur_day-$cr_day)." / $gcnt - $dirstotal <br>";
				// сигнал браузеру 1р в 10 сек
				echo "."; flush();
				if (statusupdate())
				{	echo "$dir ($gcnt)<br>\n"; flush();
				}


			    // ВЫБОР $ntops ТОПОВЫХ КАРТИНОК в галере -> $toppics[]
                if ($site=='')
			    {
					//выбрать 2 картинки по рейтингу ELSE rnd (-> thumbs)
					//выбрать 1 картинку по рейтингу / rnd (взять из thumbs / сгенерить (-> thumbs))
			    $ntops=$topthpergal; $nopics=0;
			    $toppics=array();

				// выбор rnd / топовой  карт. -> $galpic
				if (rand(0,100)>$galraternd)
				{	// rated
					$req="SELECT pic,reqs FROM ".$pics_table." WHERE galid='$galid' AND ready='1' AND thumbok='1' ORDER BY reqs DESC LIMIT $ntops"; myreq();
				}
				else
				{	// by rnd
					$req="SELECT pic,reqs FROM ".$pics_table." WHERE galid='$galid' AND ready='1' AND thumbok='1' ORDER BY RAND() DESC LIMIT $ntops"; myreq();
				}
//echo $req."<br>";
				if ($sth)
				{	$maxreqs=0;
					while ($row=mysql_fetch_row($sth))
					{	if ($maxreqs==0) $maxreqs=$row[1];
						$toppics[]=$row[0];
					}
				}
//echo "/ sz toppics:".sizeof($toppics)." / np: $nopics / $toppics[0] / $toppics[1]  / $galpic<br>";

                
				// ИЗГОТ-Е ТУМБ
//				array_unshift($toppics,$galpic); 
				$gp=1; $pi=1; // gp - galpic (first)
//echo "/ ".sizeof($toppics)." / $toppics[0] / $toppics[1]  /<br>";
                if (sizeof($toppics)>0)
                {
				foreach ($toppics as $pic)
				{
					preg_match("|(.*)\.([^\.]*)$|i",$pic, $picparts); // $picparts[1] dir, $picparts[2] name of pic
					//$picname=$picparts[1]; 
					$thname=$picparts[1];
					$thdir="$gals_dir/$dir/$pgtemplate"."thumbs";
					//$thpath="$root/$thdir/$thname.jpg";
					$thpath="$thdir/$pic.jpg";

					$numthname="$grtpadd$di-$pi";
					//$numthpath="$root/$top_thumbs/$numthname.jpg";
					$numthpath="$top_thumbs/tmp/$numthname.jpg";
					// для galpic
					if ($gp)
					{	$galpic=$pic;
						$galthname=$thname; // $dir-$galthpic.jpg - thumb gal pic
					}
//echo ":: $newpic :: $root/$dir/$pic :: ".$thpath." ::<br>";

					// СОЗДАНИЕ 1-1.jpg в topthumbs 
						// пров наличия готовой тумбы
					if ($gcnt<$topthlim)
					{	if (file_exists($numthpath) && !$remake)
						{	$newpic=0;
							if (filesize($numthpath) != filesize($thpath)) $newpic=1;
						}
						else $newpic=1;
                        if ($newpic){ copy ("$thpath", "$numthpath"); echo ".";}
						// csv файл
						if ($pggroup=='all')
						{	if ($galgroup!='' )	
								$tmp=$pgname."_".$galgroup."_0.html";
							else $tmp='';
						}
						else
							$tmp=$pgname."_".$pggroup."_$pcnt.html";                                                                                                                                                                                                                                                                         
						fputs($fhtp, "$dir;$numthname.jpg;http://".$_SERVER["SERVER_NAME"]."/$top_thumbs/$numthname.jpg;http://".$_SERVER["SERVER_NAME"]."/$gals_dir/$dir/glocind.php;http://".$_SERVER["SERVER_NAME"]."/tpic2gall.php?$numthname;http://".$_SERVER["SERVER_NAME"]."/tpic2grind.php?$numthname;http://".$_SERVER["SERVER_NAME"]."/$thpath\n");
						fputs($fhtp1, "$numthname;$dir;$tmp\n");
						if ($gp) fputs($fhtp2, "$numthname.jpg;http://".$_SERVER["SERVER_NAME"]."/$top_thumbs/$numthname.jpg;http://".$_SERVER["SERVER_NAME"]."/$gals_dir/$dir/glocind.php;http://".$_SERVER["SERVER_NAME"]."/tpic2gall.php?$numthname;http://".$_SERVER["SERVER_NAME"]."/tpic2grind.php?$numthname;http://".$_SERVER["SERVER_NAME"]."/$thpath\n");
					}
					$pi++;
					if ($gp) $gp=0;
				}
				$glink="$gals_dir/$dir/glocind.php?img=1";  //$glink="$gals_dir/$dir/glocind.php?pic=$galpic"; -стараЯ строка
				
				$thlink="$thdir/first_thumb.jpg";
//echo "$glink|$thlink<br>";
				} // sizeof $toppics > 0
				else
				{	$glink="$gals_dir/$dir/glocind.php";
					$thlink="";
				}
				} // if site==''
				else
				{	// if site!='' (external galer)
					if ($gcnt<$topthlim)
					{	// csv файл
						if ($pggroup=='all')
						{	if ($galgroup!='' )	
								$tmp=$pgname."_".$galgroup."_0.html";
							else $tmp='';
						}
						else
							$tmp=$pgname."_".$pggroup."_$pcnt.html";
						fputs($fhtp, "$dir;".tourl($thlink).";".tourl($thlink).";".tourl($glink).";http://".$_SERVER["SERVER_NAME"]."/tpic2gall.php?$numthname;http://".$_SERVER["SERVER_NAME"]."/tpic2grind.php?$numthname\n");
						fputs($fhtp1, "$numthname;".tourl($glink).";$tmp\n");
					}
					$glink="http://$site$dir"; $thlink="http://$thumb";
				} // if site!=''


				// сигнал браузеру 1р в 10 сек
				if (statusupdate())
				{	echo "."; flush();
				}
		

				// ИЗГОТОВЛЕНИЕ БЛОКОВ
				// подготовка переменных
				if ($gcnt==0)
				{	$pgcrstart=1;
					$pcnt=0; $blcnt=0; $rgcnt=0; $bgcnt=0; $pgcnt=0; 
			        $pages=ceil($dirstotal/($rows_p_page * $pics_p_row));
				}
				else
				{	if ($rowend) $rgcnt=0;
					else 		$rgcnt++;
					if ($blend){ $bgcnt=0; $blcnt++; }
					else		$bgcnt++;
					if ($pgend){ $pgcnt=0; $blcnt=0; $pcnt++; }
					else 		$pgcnt++;
				}
				// установка флагов
				// pgend
				if ( $pgcnt==($rows_p_page * $pics_p_row)-1 || $gcnt==($dirstotal-1) )	 $pgend=1;
				else $pgend=0;
					// rowstart
				if ($rgcnt==0) $rowstart=1;
				else $rowstart=0;
					// rowend
				if ( $rgcnt==$pics_p_row-1 || $pgend ) $rowend=1;
				else $rowend=0;
					// block start
				if ($bgcnt==0) $blstart=1;
				else $blstart=0;
					// block end
                if ( $bgcnt==($rows_p_block * $pics_p_row)-1 || $pgend ) $blend=1;
				else $blend=0;

//echo " >$blcnt< blstart/end: $blstart|$blend / rowstart/end: $rowstart|$rowend / >$pcnt< pgend: $pgend<br>";
//echo " pgcnt: $pgcnt | gcnt: $gcnt| blcnt"				
				// blstart
				if ($blstart)
				{	if ($bgcolor!='') $tmp="bgcolor='$bgcolor'";
					else $tmp='';
					$blocks[$blcnt]=$before1.$tmp.$before2;
				}
                
                // row start
				if ($rowstart) $blocks[$blcnt].='';
				// block fill
//echo "/ gal: $dir / time: ".(time()/(3600*24)-$cr_day)." / $newgaldays<br>";
                	// NEW подпись 

				if ($t <= $date+str2time($new_delay)) $new_sign="<font style='color:#FFFFCC; background:#FF0000; font-weight:bold'>New</font> ";
				else $new_sign='';

					// если нет описания title или dir или запретные слова
				if ($galdescr=='' || eregi($forbidden_words, $galdescr)) 
				{	$galdescr='';
					if (!eregi($forbidden_words, $galtitle)) $galdescr=trim($galtitle);
				}
				if ($galdescr=='') $galdescr=preg_replace("|[_/]|"," ",$dir);
					// if site!='' -> clickcounter
				if ($site!='')
				{	$glink="gals_clckcnt.php?".base64_encode("l=$galid");
					$photos_='';
				}
				else $photos_=" ($pics$photos)";
					

					// тумба-ссылка
				if (!isset($addcj)) $addcj='';
				if ($galgroup!='' && $pcnt==0 && $thumb2gr)
				{	if ($thlink!='') $blocks[$blcnt].="<td><a href='$addcj".$pgname.'_'.$galgroup."_0.html' $target_view><img width='$width' src=\"".tourl($thlink)."\"><br>".$new_sign.$galdescr.$photos_."</a><br></td>";
					else $blocks[$blcnt].="<td class=\"link\"><a href='$addcj".$pgname.'_'.$galgroup."_0.html' $target_view>".$new_sign.$galdescr."</a><br><font class=\"copy\">$photos_</font></div></td>";
				}
				else	// для не all || $pcnt>0 || thumb2gr
				{	if ($thlink!='') $blocks[$blcnt].="<div class='thumb'><a href=\"$addcj".tourl($glink)."\" $target_view><img width='$width' src=\"".tourl($thlink)."\"><br>".$new_sign.$galdescr.$photos_."</a></div>";
					else $blocks[$blcnt].="<td class=\"link\"><a href=\"$addcj".tourl($glink)."\" $target_view>".$new_sign.$galdescr."</a><br><font class=\"copy\">$photos_</font>ccc</td>";
				}
/*				{	if ($thlink!='') $blocks[$blcnt].="<td><a href='$addcj".$pgname.'_'.$galgroup."_0.html' $target_view><img width='$width' src=\"".tourl($thlink)."\"></a><br>".$new_sign.$galdescr."$photos_</td>";
					else $blocks[$blcnt].="<td><a href='$addcj".$pgname.'_'.$galgroup."_0.html' $target_view class=\"link\">".$new_sign.$galdescr."$photos_</a></td>";
				}
				else	// для не all || $pcnt>0 || thumb2gr
				{	if ($thlink!='') $blocks[$blcnt].="<td><a href=\"$addcj".tourl($glink)."\" $target_view><img width='$width' src=\"".tourl($thlink)."\"></a><br>".$new_sign.$galdescr."$photos_</td>";
					else $blocks[$blcnt].="<td><a href=\"$addcj".tourl($glink)."\" $target_view class=\"link\">".$new_sign.$galdescr."$photos_</a></td>";
				}*/
//echo "/block ".$blcnt."<br>";

				// row end
				if ($rowend) $blocks[$blcnt].='';

				// blend
				if ($blend)
				{	$blocks[$blcnt].=$after; // </tr></table>
				}

				// pgend
				if ($pgend)
				{	unset($pregfrom,$pregto);

					$page_title= $title;
					if ($pggroup!='all') $page_title= $grtitles[$pggroup];
					if ($pcnt!=0) $page_title=$page_title.": cтраница - ".($pcnt+1);
					 

				    // title string
					$pregfrom[]="|<!--title-->|"; $pregto[]=$page_title;
					// pages links string
					if ($pages==1) $pagesline='';
					else
					{	$pagesline='<h2>';
						$nos=nos($pcnt+1, $pages);
						foreach ($nos as $i)
                        {	
							if ($pggroup=='all' && $i==1) continue;

							if ($i==1 && $grpgnadd=='') $cnttxt='';
							else $cnttxt="_".($i-1);
											
                        	if($i == ($pcnt+1))
                                $pagesline=$pagesline."<b class='stopka_select'>".$i."</b> ";
                            else
							{	/* TO BE  CONTINUE
								$sot=(int)($i%100);
								if ($i%100)*/
								$pagesline=$pagesline."<a href='$pgname$grpgnadd$cnttxt.html' class='stopka'>".$i."</a> ";
                            }
                        }
                        unset($nos);
						$pagesline=$pagesline.'</h2>';
					}
//echo "pgline: $pagesline<br>";
					$pregfrom[]="|<!--pages-->|"; $pregto[]=$pagesline;
					// block strings
					for ($tmp=0; $tmp<=$blcnt; $tmp++)
					{	$pregfrom[]="|<!--block$tmp-->|"; $pregto[]=$blocks[$tmp];
					}
					// список групп
					$pregfrom[]="|<!--groups-->|"; $pregto[]=$grlist;

					// кнопки навигации
					$pregfrom[]="|<!--to_main-->|";
					$pregto[]= "<a class='lb' href='http://".$_SERVER["SERVER_NAME"]."'>НА ГЛАВНУЮ</a><br>";


					if (isset($html_fragments))
					{
    					//------- боковушки --------
						// html фрагменты footer, и т.д.
						foreach (array_keys($html_fragments) as $key)
						{		
							// фрагмент
							$html_files= glob($html_fragments[$key]."*.html");
							$groupped=array();
							$nongroupped=array();
//echo "<br>".$html_fragments[$key]." : ".implode(' | ',$html_files)."<br>";
							foreach ($html_files as $html_file)
							{	
								preg_match("|^([^\.]+(\.[^\.]+)?\.html$)|i",$html_file,$parts);
//                                echo $html_file."|".$parts[1].'|'.$parts[2]."<br>";
								if (isset($parts[2])) 
								{	if ($parts[2]=='.'.$pggroup) $groupped[]= $parts[1];
								}	
								else	$nongroupped[]= $parts[1];
							}
							$pregfrom[]="|<!--".$key."-->|"; 
//echo sizeof($groupped)."<>".sizeof($nongroupped)."<br>";
							if (sizeof($groupped)>0)
							{	//echo "*** groupped ***<br>";
								$tmp= $groupped[array_rand($groupped)];
								$pregto[]= file_get_contents($tmp);
//								echo " - ".$tmp."<br>";
//								$pregto[]= file_get_contents($groupped[array_rand($groupped)]);
							}
							else
							{	//echo "*** non groupped ***<br>";
								$tmp= $nongroupped[array_rand($nongroupped)];
								$pregto[]= file_get_contents($tmp);
//								echo " - ".$tmp."<br>";
//								$pregto[]= file_get_contents($nongroupped[array_rand($nongroupped)]);
							}
						}
					}

					if (isset($first_page_html_fragments) && $pcnt==0)
					{
					    //echo "pcnt=0<br>";
    					//------- встатвки для первой страницы --------
						// html фрагменты footer, и т.д.
						foreach (array_keys($first_page_html_fragments) as $key)
						{		
							// фрагмент
							$html_files= glob($first_page_html_fragments[$key]."*.html");
							$groupped=array();
							$nongroupped=array();
//echo "<br>".$first_page_html_fragments[$key]." : ".implode(' | ',$html_files)."<br>";
//echo "###1 ".sizeof($groupped)." $pggroup<br>";
							foreach ($html_files as $html_file)
							{	
//echo "###2 $html_file<br>";
								preg_match("|^([^\.]+(\.[^\.]+)?\.html$)|i",$html_file,$parts);
//echo "###3 ".$parts[1].": - ".$parts[2]."<br>";
//                                echo $html_file."|".$parts[1].'|'.$parts[2]."<br>";
								if (isset($parts[2])) 
								{	if ($parts[2]=='.'.$pggroup) $groupped[]= $parts[1];
//echo "### $pggroup: ".$parts[1].": - ".$parts[2]."<br>";
								}	
								else	$nongroupped[]= $parts[1];
							}
							
//echo sizeof($groupped)."<>".sizeof($nongroupped)."<br>";

							if (sizeof($groupped)>0 && $pggroup!='all')
							{	//echo "*** groupped ***<br>";
								$tmp= $groupped[array_rand($groupped)];
								$pregto[]= file_get_contents($tmp);
								$pregfrom[]="|<!--".$key."-->|";
//								echo "###gr $tmp<br>";        
//								echo " - ".$tmp."<br>";
//								$pregto[]= file_get_contents($groupped[array_rand($groupped)]);
							}
							if (sizeof($nongroupped)>0 && $pggroup=='all')
							{	//echo "*** first page non groupped ***<br>";
								$tmp= $nongroupped[array_rand($nongroupped)];
								$pregto[]= file_get_contents($tmp);
								$pregfrom[]="|<!--".$key."-->|";
//								echo "###ngr $tmp<br>";
//								echo " - ".$tmp."<br>";
//								$pregto[]= file_get_contents($nongroupped[array_rand($nongroupped)]);
							}
						}
					}

					if (!isset($html_fragments))
					{
						// инклюды
						$tmp=glob($toppth);
						if (sizeof($tmp)>0)
						{	$_page_top_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--top-->|"; $pregto[]=$_page_top_; unset($_page_top_);
						}
/*$fh=fopen("test",'w'); 
fwrite($fh,"|$footpth|");
fclose($fh);*/
						$tmp=glob($footpth);
						if (sizeof($tmp)>0)
						{	$_page_foot_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--footer-->|"; $pregto[]=$_page_foot_; unset($_page_foot_);
						}
						$tmp=glob($poslefootpth);
						if (sizeof($tmp)>0)
						{	$_page_poslefoot_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--poslefooter-->|"; $pregto[]=$_page_poslefoot_; unset($_page_poslefoot_);
						}
						$tmp=glob($poslecenterpth);
						if (sizeof($tmp)>0)
						{	$_page_poslecenter_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--poslecenter-->|"; $pregto[]=$_page_poslecenter_; unset($_page_poslecenter_);
						}
						$tmp=glob($posleposlecenterpth);
						if (sizeof($tmp)>0)
						{	$_page_posleposlecenter_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--posleposlecenter-->|"; $pregto[]=$_page_posleposlecenter_; unset($_page_posleposlecenter_);
						}
						$tmp=glob($docenterpth);
						if (sizeof($tmp)>0)
						{	$_page_docenter_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--docenter-->|"; $pregto[]=$_page_docenter_; unset($_page_docenter_);
						}
						$tmp=glob($leftpth);
						if (sizeof($tmp)>0)
						{	$_page_left_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--left-->|"; $pregto[]=$_page_left_; unset($_page_left_);
						}
						$tmp=glob($rightpth);
						if (sizeof($tmp)>0)
						{	$_page_right_=file_get_contents($tmp[array_rand($tmp)]);
							$pregfrom[]="|<!--right-->|"; $pregto[]=$_page_right_; unset($_page_right_);
						}
						unset($tmp);
					} // if (isset($html_fragments)) else
					
					
/*
					// outed VARS
					if ($pggroup!='all')
					{
						$pregfrom[]="|<!--var.pgnum-->|"; $pregto[]=$pcnt;
						$pregfrom[]="|<!--var.pggroup-->|"; $pregto[]=$pggroup;
						$pregfrom[]="|<!--var.pggroups-->|"; $pregto[]=$pggroups;
						$pregfrom[]="|<!--var.pggrtitle-->|"; $pregto[]=$grtitles[$pggroup];
						$pregfrom[]="|<!--var.deftitle-->|"; $pregto[]=$deftitle;
					}
*/

					// делаем страницу
					$templ=file_get_contents("$pgtemplate.html");
					$pgout=preg_replace($pregfrom,$pregto,$templ);
					if ($pcnt=='0' && $grpgnadd=='') $cnttxt='';
					else $cnttxt="_$pcnt";
					$fhpg=fopen("$pgname$grpgnadd$cnttxt.html.new","w+");
					fputs($fhpg, $pgout); fclose($fhpg);
				}
		    	
		    	if (!$nopics) $di++;
			}	// $dir
		}	// if dirs>0
	}	// groups
/*

pics_p_row
rows_p_block
rows_p_page 


<gtotal>
pcnt=0; pgcnt=0; rgcnt=0; blcnt=0; bgcnt=0; blstart=1; pgstart=1;

<read gal> gcnt++;


if gcnt!=0 
	if rowend	rgcnt=0;
	else 		rgcnt++;
	if blend	bgcnt=0; blcnt++;
	else		bgcnt++; 
	if pgend	pgcnt=0; blcnt=0; pcnt++;
	else 		pgcnt++;


	// pgend
if ( pgcnt==(rows_p_page * pics_p_row)-1 || gcnt==(gtotal-1) )	-> pgend=1;
else !rowend

	// rowstart
if rgcnt==0	-> rowstart
else !rowstart
	// rowend
if ( rgcnt==pics_p_row-1 || pgend ) 	-> rowend
else !rowend

	// block start
if bgcnt==0			-> blstart
else !blstart
	// block end
if ( bgcnt==(rows_p_block * pics_p_row)-1 || pgend )		-> blend
else !rowend


if blstart block start
	<table>

if rowstart 
	<tr>

block fill

if rowend
	</tr>

if blend 	
	</table> -> blocks[]

if pgend 
    linksline
        pages=ceil(galstotal/(rows_p_page * pics_p_row));
		for tmp=0 to <pages
			if tmp==pgcnt font bold
	blocks[] -> preg -> page_<group>_<pgcnt>_

------
morda: title для каждой group

*/
	fclose($fhtp); fclose($fhtp1); fclose($fhtp2);

	// topthumbs & csv old files & modify pg
	chdir("$top_thumbs/tmp");
	$tmp=glob("*.jpg");
	foreach($tmp as $mf)
	{	if (file_exists("../$mf"))
		{	$newpic=0;
			if (filesize($mf) != filesize("../$mf")) $newpic=1;
		}
		else $newpic=1;
        if ($newpic) copy ("$mf", "../$mf");
	}


	chdir("../");
//	$tmp=glob("*.csv");
//	foreach($tmp as $mf) unlink($mf);
	$tmp=glob("*.csv.new");
	foreach($tmp as $mf)
	{	preg_match("|^(.*[^\.])\.new$|i",$mf, $mpnp); // $mpnp[1] morda_*.html
		if (!rename($mf, $mpnp[1]))
		{	unlink($mpnp[1]);
			rename($mf, $mpnp[1]);
		}
	}
	copy ("top_thumbs.csv", "../top_thumbs.csv");
	copy ("top_thumbs1.csv", "../top_thumbs1.csv");


	chdir("$root");
//	$tmp=glob($pgname."*.html");
//	foreach($tmp as $mf){ unlink($mf); echo "kill: $mf<br>"; }
	$tmp=glob("$pgname*.new");
	foreach($tmp as $mf)
	{	preg_match("|^(.*[^\.])\.new$|i",$mf, $mpnp); // $mpnp[1] morda_*.html
		if (!rename($mf, $mpnp[1]))
		{	unlink($mpnp[1]);
			rename($mf, $mpnp[1]);
		}
//echo "$mf -> $mpnp[1]<br>"; 
	}

echo "<br>".(time()-$t)." seconds";
statusupdate('end');
    
}   
    
    
//-----------------------------------------
// tourl
function tourl($fn)
{	$tmp=preg_replace("&(%3D|%3F|%26|%2F)&e", "urldecode('\\1')", urlencode($fn)); $tmp=preg_replace("|\+|","%20",$tmp);
	return ($tmp);
}
//-----------------------------------------
// str to time
function str2time($str)
{	preg_match("&\s*(\d+)\s*(d|h|m|s)?\s*&i", $str, $parts);
    if (!isset($parts[2])) $parts[2]='';
    $parts[2]=strtolower($parts[2]);
	switch ($parts[2])
	{	case '': $time=$parts[1]; break;
		case 's': $time=$parts[1]; break;
		case 'm': $time=60*$parts[1]; break;
		case 'h': $time=3600*$parts[1]; break;
		case 'd': $time=86400*$parts[1]; break;
	}
	return $time;
}
//-----------------------------------------
// динамичская строка (curr, end)
function nos($curr, $end)
{	$span=14; $spanP2=ceil($span/2);

	$nos=array();
	$lstart=$curr-$spanP2;
	if ($lstart<1) $lstart=1;
	$lend=$curr+$spanP2;
	if ($lend>$end) $lend=$end;
	
	if ($lstart>=$span)
	{	$add=$span;
		$curr=$lstart-$add;
		while ($curr>0)
		{	array_unshift($nos, floor($curr));
			$add*=2; $curr=$lstart-$add;
		}
		array_unshift($nos, 1);
	}
	else $lstart=1;
	for ($curr=$lstart; $curr<=$lend; $curr++) $nos[]=$curr;
	if ($lend<$end)
	{	$add=$span;
		$curr=$lend+$add;
		while ($curr<$end)
		{	$nos[]=$curr;
			$add*=2; $curr=$lend+$add;
		}
		$nos[]=$end;
	}
	return($nos);
}
//--------------------------
function log_str($str)
{
	$fh=fopen("log", "w+");
	fputs($fh, $str);
	fclose($fh);
}
//--------------------------
function log_str_cont($str)
{
	$fh=fopen("log", "a+");
	fputs($fh, $str);
	fclose($fh);
}


?>