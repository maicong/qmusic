<?php
/**
 *
 * 音乐听
 *
 * @package custom
 * @author  MaiCong <i@maicong.me>
 * @link    https://github.com/maicong/stay
 * @since   0.3.3
 */

require_once(__DIR__. '/Http_Client.php');

$client = new Http_Client();

function HttpGet($url, $referer) {
  global $client;
  $client->setHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36')
          ->setHeader('Referer', $referer)
          ->setTimeout(10)
          ->send($url);
  return $client->getResponseBody();
}

function getLrc ($songid) {
    $data = HttpGet('http://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg?musicid=' . $songid . '&pcachetime=' . time() . '&format=jsonp', 'http://music.qq.com/miniportal/player_lyrics.htm?songid=' . $songid);
    if (!empty($data)) {
        preg_match('/lyric":\s?"([^"]+?)"/is', $data, $matche);
        if ($matche && !empty($matche[1])) {
            return base64_decode($matche[1]);
        }
    }
}

function getVkey () {
  $data = HttpGet('http://base.music.qq.com/fcgi-bin/fcg_musicexpress.fcg?json=3&guid=5150825362&format=json', 'http://y.qq.com/');
  if(!empty($data)) {
      $vkey = json_decode($data, true);
      return $vkey ? $vkey['key'] : '';
  }
}

function getMusic ($disstid) {
    $liked = HttpGet(
      'https://c.y.qq.com/qzone/fcg-bin/fcg_ucc_getcdinfo_byids_cp.fcg?type=1&json=1&utf8=1&onlysong=1&format=json&loginUin=153775357&inCharset=utf8&outCharset=utf-8&platform=yqq&needNewCode=0&song_begin=0&song_num=100&disstid=' . $disstid,
      'https://y.qq.com/n/yqq/playlist/' . $disstid . '.html'
    );
    $vkey = getVkey();
    if (!empty($liked)) {
        $data = json_decode($liked, true);
        if (!$data || !is_array($data)) {
            return;
        }
        $music = [];
        foreach ($data['songlist'] as $key => $val) {
            $singer = [];
            foreach ($val['singer'] as $k=>$v) {
                $singer[$k] = $v['name'];
            }
            if (!empty($val['songmid'])) {
                array_push($music, [
                    'songid' => $val['songid'],
                    'songmid' => $val['songmid'],
                    'songname' => $val['songorig'],
                    'albummid' => $val['albummid'],
                    'albumname' => $val['albumname'],
                    'singer' => implode(',', $singer),
                    'duration' => $val['interval'],
                    'vkey' => $vkey
                ]);
            }
        }
        return $music;
    }
}

function getRadios ($rid = '') {
    $radios = [];
    if ($rid === '1') {
        return getMusic('6177150873');
    }
    $vkey = getVkey();
    if (!$rid) {
        $radiolist = HttpGet('http://proxy.music.qq.com/3gmusic/fcgi-bin/get_radiolist?cid=294&ct=18', 'http://y.qq.com/');
        if(empty($radiolist)) {
            return;
        }
        $list = json_decode($radiolist, true);
        if (!$list || empty($list['Group'])) {
            return;
        }
        foreach ($list['Group'] as $key => $val) {
            foreach ($val['List'] as $k => $v) {
                if ($v['RecID'] === 99) {
                    $radios[0] = [
                        'rid' => '1',
                        'pic' => 'https://y.gtimg.cn/music/common/upload/t_musichall_pic/363459.jpg',
                        'name' => '2019'
                    ];
                } else {
                    if (in_array($v['RecID'], [259, 194, 212, 274, 275, 285, 213, 214, 256, 208, 200, 179, 180, 189, 187, 148, 142, 186, 183, 135])) {
                        continue;
                    }
                    $radios[$v['RecID']] = [
                        'rid' => $v['RecID'],
                        'pic' => str_replace('http://', 'https://', $v['PicUrl']),
                        'name' => base64_decode($v['RecName'])
                    ];
                }
            }
        }
        $radios = array_values($radios);
    } else {
        $radiolist = HttpGet('http://c.y.qq.com/v8/fcg-bin/fcg_v8_radiosonglist.fcg?format=json&labelid=' . $rid, 'http://y.qq.com/');
        if(empty($radiolist)) {
            return;
        }
        $list = json_decode($radiolist, true);
        if (!$list || empty($list['data'])) {
            return;
        }
        foreach ($list['data'] as $key => $val) {
            $singer = [];
            foreach ($val['singer'] as $k => $v) {
                $singer[$k] = $v['name'];
            }
            $radios[$key] = [
                'songid' => $val['id'],
                'songmid' => $val['mid'],
                'songname' => $val['name'],
                'albummid' => $val['album']['mid'],
                'albumname' => $val['album']['name'],
                'singer' => implode(',', $singer),
                'duration' => $val['interval'],
                'vkey' => $vkey
            ];
        }
    }
    return $radios;
}

if ('XMLHttpRequest' === $client->getServer('HTTP_X_REQUESTED_WITH')){
    // $ref = parse_url(strtolower($client->getReferer()));
    // if (empty($ref['host']) || $ref['host'] !== 'maicong.me') {
    //     $client->throwJson([
    //         'data' => '非正常人类的请求啊，为了减少不必要的压力，我就什么数据也不给你'
    //     ]);
    //     exit;
    // }
    if ($client->get('do') === 'getLrc') {
        $songid = $client->get('songid');
        $client->throwJson([
            'data' => $songid ? getLrc($songid) : ''
        ]);
    }
    if ($client->get('do') === 'getRadios') {
        $rid = $client->get('rid');
        $client->throwJson([
            'data' => getRadios($rid)
        ]);
    }
    exit;
}
?>
<!doctype html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>音乐听 - MaiCong</title>
        <meta name="description" content="音乐听，听我喜欢听">
        <meta name="author" content="MaiCong (maicong.me)">
        <meta name="renderer" content="webkit">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black">
        <meta name="format-detection" content="telephone=no">
        <meta name="apple-mobile-web-app-title" content="音乐听 - MaiCong">
        <meta name="application-name" content="音乐听 - MaiCong">
        <meta name="baidu-site-verification" content="ceEXCE8Jum">
        <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon-180x180.png">
        <link rel="stylesheet" href="style.css">
    </head>
<body>
    <!--[if lte IE 9]>
        <script type="text/javascript">
            document.body.innerHTML='';
            document.body.style.background='#000';
            alert('\u4e0d\u652f\u6301\u7684\u6d4f\u89c8\u5668\u7248\u672c\uff01\u518d\u89c1\uff01');
            window.open('','_self','');
            window.close();
        </script>
    <![endif]-->
    <div id="music-loading" class="loading">
        <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#fff"><g transform="translate(1 1)" stroke-width="2" fill="none" fill-rule="evenodd"><circle stroke-opacity=".5" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></svg>
    </div>
    <div id="music-bgimg" class="bgimg">
        <span class="mask"></span>
    </div>
    <main id="music" class="music">
        <div class="music-cover">
            <div id="music-radio" class="music__radio"><i></i><span>电台</span></div>
            <div id="music-pic" class="music__pic">
                <img src="/img/music-c513f7.jpg">
            </div>
            <div class="music__control">
                <div class="time">
                    <span id="music-ctime">00:00</span> / <span id="music-dtime">00:00</span>
                </div>
                <div class="progress">
                    <span id="music-played" class="played"><i></i></span>
                    <span id="music-loaded" class="loaded"></span>
                </div>
                <div class="menu">
                    <div id="music-btn" class="music__btn">
                        <div id="music-prev" class="btn prev" title="上一首"><i></i></div>
                        <div id="music-play" class="btn play" title="播放"><i></i></div>
                        <div id="music-next" class="btn next" title="下一首"><i></i></div>
                    </div>
                    <div class="music__menu__more">
                        <div class="voice">
                            <div class="volume"><span id="music-volume"></span></div>
                            <div id="music-mute" class="mute" title="音量">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                        <div id="music-showlist" class="showlist" title="歌曲列表">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="music-view" class="music-viewport">
            <div id="music-radios" class="view view__radios"></div>
            <div id="music-list" class="view view__list"></div>
            <div id="music-detail" class="view view__detail view__active">
                <h2 class="title"></h2>
                <div class="info">
                    <span>歌手：<i class="singer"></i></span>
                    <span>专辑：<i class="album"></i></span>
                </div>
                <div class="lyric">
                    <div id="music-lrc" class="lyric__inner"></div>
                </div>
            </div>
        </div>
    </main>
    <footer class="footer">
        <p>&copy; 2012-<?php echo date('Y'); ?> v0.3.4 MaiCong</p>
    </footer>
<script src="/vendor.js"></script>
<script src="/player.js"></script>
<script>
if (window.localStorage) {
  const upTime = window.localStorage.getItem('musicUptime')
  const nowTime = new Date().getTime()
  if (!upTime || 604800 < upTime - nowTime) {
    window.localStorage.clear()
    window.localStorage.setItem('musicUptime',nowTime)
  }
}
</script>
<script src="https://w.cnzz.com/c.php?id=5891594" async></script>
</body>
</html>
