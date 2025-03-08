<?php

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../config.php';
include '../functions.php';
include '../libs/simple_html_dom.php';
$mysqli->set_charset('utf8mb4');

$site_url = 'https://babepedia.com/';





/*


<div id='thumbs' class='thumbsblock'>
<span class="thumbshot">
   <a href="/babe/Zuarash"><img class="thumbimg lazy" border="0" data-src="/images/photo-placeholder-small.png" alt="Zuarash"></a>
   <div class="thumbtext"><img src='/img/charts_down.gif' alt='702 place(s) down' class='listarrow'>#86301: <a href="/babe/Zuarash">Zuarash</a></div>
</span>
</div>
*/

// find babe profiles
if(0)
{

    $lastpage = 40;

    for($i=$lastpage; $i>0; $i--)
    {
file_put_contents('pageno.txt',$i);
echo "<div style='background-color: yellow'>Working page {$i}</div>";
        $url = "https://www.babepedia.com/top100?page=".$i;

        $resp = func_get_content($url);
        $dom = new simple_html_dom($resp);

        // title
        echo $dom->find("title",0)->innertext."\n";


        $profs = $dom->find("div#thumbs span.thumbshot");

        foreach($profs as $prof)
        {
            $a = $prof->find("a",0);
            $link = $a->href;
           // echo $a;exit();

            $img = $a->find("img",0);
            $photo = '';
            if(isset($img->attr['data-src']))
            {
                $photo = $img->attr['data-src'];
            }else{
                $photo = $img->attr['src'];
            }

            $name = $img->alt;
            $name = preg_replace('/[^\x00-\x7F]/', '', $name);
            $name = $mysqli->real_escape_string($name);
            $photo = $mysqli->real_escape_string($photo);

           // echo $name."\n"; exit();
            $origslug = basename($link);
            $slug = slugify($origslug);


            $origslug = base64_encode($origslug);
            $origslug = $mysqli->real_escape_string($origslug);

            $slug = $mysqli->real_escape_string($slug);

            $xq = $mysqli->query("SELECT * FROM `babe` WHERE `slug`='{$slug}' LIMIT 1");
            if($xq->num_rows == 0)
            {
                $mysqli->query("INSERT INTO `babe` (`slug`, `origslug`, `name`, `photo`) VALUES ('{$slug}', '{$origslug}', '{$name}', '{$photo}')");
            }else{
               // $mysqli->query("UPDATE `babe` SET `name`='{$name}', `photo`='{$photo}' WHERE `origslug`='{$origslug}' LIMIT 1");
            }
            
        }

// exit();
    }

}

// update babe page data

if(isset($_REQUEST['bd']))
{
    $xq = $mysqli->query("SELECT * FROM `babe` where title is null order by id desc limit 1000");
    while($row = $xq->fetch_assoc()) {
        $slug = $row['slug'];
        $origslug = $row['origslug'];
        $origslug = base64_decode($origslug);
        $url = "https://www.babepedia.com/babe/{$origslug}";
        echo $url;
        $resp = func_get_content($url);
        $html = str_get_html($resp);

        $data = [];

        if(strpos($html,'og:title') > 0)
        {


        }else{

            if(strpos($resp,"Sorry, she wasn't found in our database.")>0)
            {
                $mysqli->query("DELETE FROM `babe` WHERE id = '{$row['id']}'");
            }

            sleep(1);
            //echo $resp;
            continue;
           // exit();
        }

        $ogtitle = $html->find("meta[property='og:title']",0)->content;
        $ogdesc = $html->find("meta[property='og:description']",0)->content;
       // $ogimg = $html->find("meta[property='og:image']",0)->content;
        $ogurl = $html->find("meta[property='og:url']",0)->content;

        $bioarea = $html->find("div#bioarea",0);
        if($bioarea)
        {
            $data['name'] = $bioarea->find('h1#babename', 0)->plaintext ?? '';

// Extract alias information
            $data['aliases'] = [];
            foreach ($bioarea->find('div#aliasinfo .aliasbox') as $aliasBox) {
                $data['aliases'][] = [
                    'an' => $aliasBox->find('.aliasname', 0)->plaintext ?? '',
                    'as' => $aliasBox->find('.aliassite', 0)->plaintext ?? '',
                ];
            }

// Extract the rating
            $data['rating'] = $bioarea->find('div#tn15rating .general.rating b', 0)->plaintext ?? '';
// Extract general bio information from the <ul id="biolist">
            $data['bio'] = [];
            foreach ($bioarea->find('ul#biolist li') as $item) {
                $label = $item->find('span.label', 0)->plaintext ?? '';
                $value = trim(str_replace($label, '', $item->plaintext));
                $data['bio'][$label] = $value;
            }

// Extract achievements
            $data['achievements'] = [];
            foreach ($bioarea->find('ul.achievements li') as $achievement) {
                $data['achievements'][] = $achievement->plaintext;
            }
        }



        $socials = $html->find("div#socialicons",0);
        if($socials)
        {
            $socialsa = $socials->find('a');
            foreach ($socialsa as $socialIcon) {
                $platform = $socialIcon->getAttribute('class');
                $platform = str_replace('proficon', '', $platform);
                $url = $socialIcon->getAttribute('href');
                $data['social_links'][] = [
                    'platform' =>trim($platform),
                    'url' => $url
                ];
            }
        }

        $profimg = $html->find("div#profimg",0);
        if($profimg)
        {
            $data['full_image_url'] = $profimg->find('a.img', 0)->href ?? '';
// Extract thumbnail URL
            $data['thumbnail_url'] = $profimg->find('img', 0)->src ?? '';
        }

        $profSelectDiv = $html->find('div#profselect', 0);
        if($profSelectDiv)
        {
            foreach ($profSelectDiv->find('div.prof') as $prof) {
                $image = [];
                $image['full_image_url'] = $prof->find('a.img', 0)->href ?? '';
                $image['thumbnail_url'] = $prof->find('img', 0)->src ?? '';
                $data['profimages'][] = $image;
            }
        }


        $galleryDiv = $html->find('div.gallery.useruploads', 0);


// Loop through each `div.thumbnail` to extract image details
        if($galleryDiv)
        {
            foreach ($galleryDiv->find('div.thumbnail') as $thumbnail) {
                $image = [];
                $image['full_image_url'] = $thumbnail->find('a.img', 0)->href ?? '';
                $image['thumbnail_url'] = $thumbnail->find('img', 0)->src ?? '';
                $data['userimages'][] = $image;
            }
        }



        $userslnks = $html->find("div.linkstable",0);
        if($userslnks)
        {
           $lnks = $userslnks->find("td a");
           foreach ($lnks as $lnk)
           {
               $data['userlinks'][] = $lnk->href;
           }
        }


        $content = $html->find("div.babebanner.separate",0);
        if($content)
        {
            $data['about'] = $content->innertext;
        }


        $jsnxdata =  $mysqli->real_escape_string(json_encode($data));
        $ogtitle = $mysqli->real_escape_string($ogtitle);


        $stat = 1;
        if($row['photo'] == '/images/photo-placeholder-small.png')
        {
            $stat = 0;
        }


        $utime = time();
        $mysqli->query("UPDATE `babe` SET `title`='{$ogtitle}',`utime`='{$utime}',`data`='{$jsnxdata}',`stat`='{$stat}' WHERE id = '{$row['id']}' LIMIT 1");


        echo '<pre>';
        echo $ogtitle;
     //   print_r($data);
        echo '</pre>';


    }

}

// gallery

if(0)
{
    $url = "https://www.babepedia.com/topgalleries?page=157";

    $lastpage = 157;
    $lastpage = 2;
    for ($i = $lastpage; $i >= 1; $i--) {

        echo "<div style='background-color: yellow'>Working page {$i}</div>";
        file_put_contents('galleryindex.txt', $i);


        $url = "https://www.babepedia.com/topgalleries?page=".$i;

        $resp = func_get_content($url);
        $dom = new simple_html_dom($resp);
        $galscards  = $dom->find("div#thumbs2 div.thumbshot");

        foreach($galscards as $galcard) {
          $a = $galcard->find('a',0);
          $span = $galcard->find('span',0);
          $img = $a->find('img',0);
          $photo = '';
          if($img)
          {
              if(isset($img->attr['data-src']))
              {
                  $photo = $img->attr['data-src'];
              }else{
                  $photo = $img->attr['src'];
              }
          }

          $href = $a->href;
          $gid = 0;
          $slug = 0;
          // /gallery/Vendela_Lindblom/377295
          $ux = explode('/', $href);
          $slug = slugify($ux[2]);
          $gid = $ux[3];


          $title = $span->plaintext;
          $title = $mysqli->real_escape_string($title);
          $origslug = $mysqli->real_escape_string(base64_encode($href));
          $photo = $mysqli->real_escape_string($photo);

          // chk
          $xq = $mysqli->query("SELECT * FROM `gallery` WHERE `gid`='{$gid}' LIMIT 1");
          if($xq->num_rows > 0)
          {
              continue;
          }



          $mysqli->query("INSERT INTO `gallery`(`slug`, `origslug`, `gid`, `photo`, `title`) VALUES ('{$slug}', '{$origslug}', '{$gid}', '{$photo}', '{$title}')");

        }

      //  exit();

    }

}
// get gallery data
if(isset($_REQUEST['gd']))
{
    // https://www.babepedia.com/ajax-fetch-babeinfo.php?id=2915


    $xq = $mysqli->query("SELECT id,gid,slug,origslug FROM `gallery` where data is null order by id desc limit 1000");
    while($row = $xq->fetch_assoc()) {

        $gid = $row['gid'];
        $slug = $row['slug'];
        $origslug = $row['origslug'];
        $origslug = base64_decode($origslug);
        $url = "https://www.babepedia.com{$origslug}";
        echo $url ."\n";
        $resp = func_get_content($url);
        $html = str_get_html($resp);

        $data = [];

        if(strpos($html,'og:title') > 0) {

        }else{
            sleep(1);
            //echo $resp;
            continue;
           // exit();
        }

        // meta title
        $ogtitle = $html->find("title",0)->innertext;
        // meta description
        $ogdesc = $html->find("meta[name='description']",0)->content;
        $ogimage = $html->find("meta[property='og:image']",0)->content;

        $data['title'] = $ogtitle;
        $data['description'] = $ogdesc;
        $data['image'] = $ogimage;



        $body = $html->find("div#gallery",0);

        $models = [];
        if($body)
        {
            $modlins = $body->find("h2 a");
            if($modlins)
            {
                foreach ($modlins as $modlin) {

                    $modelid = 0;
                    if(strpos($modlin->href,'babe/') > 0)
                    {
                        $modelid = $modlin->attr['data-babeid'];
                        $models[$modelid] = slugify(basename($modlin->href));
                    }
                }
                $data['models'] = $models;
            }
            $imgs = $body->find("a.img");
            if($imgs)
            {
                $images = [];

                foreach ($imgs as $img) {
                    $fullurl = $img->href;
                    $thumburl = $img->find("img",0)->src;
                    $imgtitle = $img->find("img",0)->alt;
                    $images[] = [$fullurl, $thumburl, $imgtitle];
                }
                $data['images'] = $images;
            }

        }

     //   print_r($data);
        $datax = $mysqli->real_escape_string(json_encode($data));
        $mysqli->query("UPDATE `gallery` SET `data`='{$datax}' WHERE `id`='{$row['id']}' LIMIT 1");

    }



}