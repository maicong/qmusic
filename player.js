(() => {
  window.FastClick.attach(document.body)

  let audio
  const audioCache = {}
  const $ = window.$
  const $musicBgImg = $('#music-bgimg')
  const $musicRadio = $('#music-radio')
  const $musicRadios = $('#music-radios')
  const $musicPic = $('#music-pic')
  const $musicCtime = $('#music-ctime')
  const $musicDtime = $('#music-dtime')
  const $musicPlayed = $('#music-played')
  const $musicLoaded = $('#music-loaded')
  const $musicPrev = $('#music-prev')
  const $musicPlay = $('#music-play')
  const $musicNext = $('#music-next')
  const $musicVolume = $('#music-volume')
  const $musicMute = $('#music-mute')
  const $musicShowlist = $('#music-showlist')
  const $musicView = $('#music-view')
  const $musicList = $('#music-list')
  const $musicDetail = $('#music-detail')
  const $musicLrc = $('#music-lrc')
  const $musicLoading = $('#music-loading')

  class Player {
    constructor (elmt, options) {
      this.settings = $.extend({
        songlist: [{
          songid: '',
          songmid: '',
          songname: '',
          albummid: '',
          albumname: '',
          singer: '',
          duration: '',
          vkey: ''
        }],
        showlrc: true,
        useRadio: false,
        autoplay: false,
        playIndex: 0,
        cacheTime: 86400,
        loop: 'list',
        preload: 'metadata'
      }, options)
      this.total = this.settings.songlist.length
      this.playIndex = this.settings.playIndex
      this.cacheTime = this.settings.cacheTime
      this.loadedTime = null
      this.playTime = null
      this.lrcTime = null
      this.soundTime = null
      this.playing = false
      this.ended = false
      this.radioId = 0
      this.storage = window.localStorage
      this.support = !!(document.createElement('audio').canPlayType('audio/mpeg'))
      this.isMobile = !!/(Android|webOS|Phone|iPad|iPod|BlackBerry|Windows Phone)/i.test(navigator.userAgent)
      this.radioUrl = `${document.URL}?do=getRadios`
      this.picUrl = 'https://qzonestyle.gtimg.cn/music/photo_new/T002R500x500M000{albummid}.jpg'
      this.lrcUrl = `${document.URL}?do=getLrc&songid={songid}`

      if (this.settings.useRadio) {
        this._radio()
      } else {
        this._init()
      }

      const self = this
      $musicPlay.on('click', function () {
        if (!audio) return
        if ($(this).hasClass('play')) {
          self.trigger('play')
        } else if ($(this).hasClass('pause')) {
          self.trigger('pause')
        }
      })

      $musicPrev.on('click', () => {
        if (!audio) return
        self.trigger('prev')
      })

      $musicNext.on('click', () => {
        if (!audio) return
        self.trigger('next')
      })

      $musicMute.on('click', function () {
        if (!audio) return
        if (audio.muted) {
          audio.muted = false
          self._updateBar('volume', self.volume)
          $(this).removeClass('mute-off')
        } else {
          audio.muted = true
          self._updateBar('volume', 0)
          $(this).addClass('mute-off')
        }
      })

      $musicList.on('click', 'li', function () {
        if (!audio) return
        self.playIndex = $(this).index()
        self.autoPlaying = !!self.playing
        self.trigger('pause')
        audio.pause()
        self._goto($(this).index())
      })

      $musicVolume.parent().on('click', function (e) {
        if (!audio) return
        const offX = self._getOffsetX(e)
        const barWidth = $(this).width()
        const percentage = parseFloat(offX / barWidth).toFixed(2)
        if (!audio) return
        self.volume = percentage
        audio.volume = percentage
        self._storageSet('musicVolume', percentage)
        self._updateBar('volume', percentage)
      })

      $musicPlayed.parent().on('click', function (e) {
        if (!audio) return
        const offX = self._getOffsetX(e)
        const barWidth = $(this).width()
        const percentage = parseFloat(offX / barWidth).toFixed(2)
        if (!audio) return
        audio.currentTime = percentage * audio.duration
        self._updateTime('ctime', percentage * audio.duration)
        self._updateBar('played', percentage)
      })

      $musicShowlist.on('click', () => {
        if (!audio) return
        if ($('body').width() < 641) {
          $musicPic.addClass('music__trans__left')
          $musicView.addClass('music__trans__none')
        }
        $musicRadios.removeClass('view__active')
        $musicList.removeClass('view__hide').toggleClass('view__active')
        $musicDetail.removeClass('view__hide').toggleClass('view__active')
      })

      $musicRadio.on('click', () => {
        if ($('body').width() < 641) {
          $musicPic.toggleClass('music__trans__left')
          $musicView.toggleClass('music__trans__none')
          $musicList.addClass('view__hide')
          $musicDetail.addClass('view__hide')
          $musicRadios.addClass('view__active')
        } else {
          $musicList.toggleClass('view__hide')
          $musicDetail.toggleClass('view__hide')
          $musicRadios.toggleClass('view__active')
        }
      })

      $musicRadios.on('click', 'li', function () {
        const rid = $(this).data('rid')
        if ($.isNumeric(rid)) {
          self.trigger('pause')
          audio.pause()
          $musicRadios.find('li').removeClass('playing')
          $(this).addClass('playing')
          self.radioId = rid
          self._storageSet('musicRadioId', rid)
          self._getSongList(rid, true)
        }
      })

      window.Mousetrap.bind('space', () => {
        $musicPlay.trigger('click')
      })
      window.Mousetrap.bind('left', () => {
        if (!audio) return
        this.trigger('prev')
      })
      window.Mousetrap.bind('right', () => {
        if (!audio) return
        this.trigger('next')
      })
    }

    _init () {
      const playIndex = this._storageGet('musicPlayIndex')
      if (playIndex) {
        this.playIndex = playIndex
      }
      const songs = this.settings.songlist[this.playIndex]

      this._updateList(this.settings.songlist)

      if (!songs.songid || !songs.songmid) {
        if (this.total > 1) {
          this.settings.songlist.splice(this.playIndex, this.playIndex + 1)
          this.next()
        } else {
          this.error()
        }
      } else {
        this._goto(this.playIndex)
      }
    }

    _loading (status) {
      if (status) {
        $musicLoading.removeClass('loading__over')
      } else {
        if (this.loadedTime) {
          clearTimeout(this.loadedTime)
        }
        this.loadedTime = setTimeout(() => {
          $musicLoading.addClass('loading__over')
        }, 500)
      }
    }

    _audio (srcs) {
      let _audio
      let _source
      const cacheKey = srcs.toString()

      if (audioCache[cacheKey]) {
        _audio = audioCache[cacheKey]
      } else {
        _audio = document.createElement('audio')
        srcs.forEach(src => {
          _source = document.createElement('source')
          _source.src = src
          _audio.appendChild(_source)
        })
        audioCache[cacheKey] = _audio
      }
      return _audio
    }

    _secondToTime (second) {
      const min = parseInt(second / 60)
      const sec = parseInt(second - min * 60)
      const add0 = num => num < 10 ? `0${num}` : `${num}`
      return `${add0(min)}:${add0(sec)}`
    }

    _getOffsetX (event) {
      event = event || window.event
      const target = event.target || event.srcElement
      return event.offsetX || (event.clientX - target.getBoundingClientRect().left)
    }

    _validTime (data) {
      if (!data || !data.hasOwnProperty('data') || !data.hasOwnProperty('timestamp')) {
        return
      }
      return data.timestamp + this.cacheTime > Date.parse(new Date()) / 1000
    }

    _parseLrc (lrc) {
      const lyric = lrc.split('\n')
      const lyricLen = lyric.length
      let lrcTimes = null
      let lrcText = ''
      let lrcHTML = ''
      const lrcs = []
      for (var i = 0; i < lyricLen; i++) {
        lrcTimes = lyric[i].match(/\[(\d{2}):(\d{2})\.(\d{2,3})]/g)
        lrcText = lyric[i].replace(/\[(\d{2}):(\d{2})\.(\d{2,3})]/g, '').replace(/^\s+|\s+$/g, '')

        if (lrcTimes != null) {
          const timeLen = lrcTimes.length
          for (let j = 0; j < timeLen; j++) {
            const oneTime = /\[(\d{2}):(\d{2})\.(\d{2,3})]/.exec(lrcTimes[j])
            const lrcTime = (oneTime[1]) * 60 + parseInt(oneTime[2]) + parseInt(oneTime[3]) / ((`${oneTime[3]}`).length === 2 ? 100 : 1000)
            lrcs.push([lrcTime, lrcText])
          }
        }
      }

      lrcs.sort((a, b) => a[0] - b[0])

      for (var j = 0; j < lrcs.length; j++) {
        if (!lrcs[j][1]) {
          continue
        }
        const cls = (j === 0 && lrcs.length > 1) ? ' class="active"' : ''
        lrcHTML += `<p${cls} data-time="${lrcs[j][0]}">${lrcs[j][1]}</p>`
      }

      $musicLrc.html(lrcHTML).fadeIn(200)
    }

    _storageGet (key) {
      if (this.storage) {
        return $.parseJSON(this.storage.getItem(key))
      }
    }

    _storageSet (key, val) {
      if (!this.storage) {
        return
      }
      if (Math.round(JSON.stringify(this.storage).length / 1024) > 1000) {
        this.storage.clear()
      }
      return this.storage.setItem(key, JSON.stringify(val))
    }

    _random (array) {
      return array[Math.floor(Math.random() * array.length)]
    }

    _fadeInSound (audio, duration) {
      const self = this
      const delay = duration / 10
      const volume = audio.volume + 0.1
      if (this.soundTime) {
        clearTimeout(this.soundTime)
      }
      if (volume <= this.volume) {
        audio.volume = volume
        this.soundTime = setTimeout(() => {
          self._fadeInSound(audio, duration)
        }, delay)
      }
    }

    _fadeOutSound (audio, duration) {
      const self = this
      const delay = duration / 10
      const volume = audio.volume - 0.1
      if (this.soundTime) {
        clearTimeout(this.soundTime)
      }
      if (volume >= 0) {
        audio.volume = volume
        this.soundTime = setTimeout(() => {
          self._fadeOutSound(audio, duration)
        }, delay)
      } else {
        audio.pause()
      }
    }

    _updateInfo (song) {
      song = $.extend({
        songname: '暂无歌曲名',
        singer: '未知',
        albumname: '未知',
        pic: '/img/music-c513f7.jpg'
      }, song)
      $musicBgImg.css({
        'background-image': `url(${song.pic})`
      })
      $musicPic.find('img').attr('src', song.pic).fadeIn(200)
      $musicDetail.find('.title').text(song.songname).attr('title', song.songname).fadeIn(200)
      $musicDetail.find('.singer').text(song.singer).attr('title', song.singer).fadeIn(200)
      $musicDetail.find('.album').text(song.albumname).attr('title', song.albumname).fadeIn(200)
      document.title = `${song.songname} - ${song.singer} - 音乐听`
    }

    _updateList (list) {
      if (!$.isArray(list)) {
        this.trigger('error', '(ーー゛) 无效的歌曲列表')
        return
      }
      const self = this
      let __temp = `<h3 class="title">歌曲列表 <small>(共${list.length}首)</small></h3><ul>`
      $.each(list, (i, {songname, singer, duration}) => {
        __temp += `<li><div class="name" title="${songname}">${songname}</div><div class="singer" title="${singer}">${singer}</div><div class="time">${self._secondToTime(duration)}</div></li>`
      })
      __temp += '</ul>'
      $musicList.html(__temp)
    }

    _updateRadios (radios) {
      if (!$.isArray(radios)) {
        this.trigger('error', '(ToT)/ 找不到电台')
        return
      }
      let __temp = ''
      let radioId = this._storageGet('musicRadioId')

      if (!radioId) {
        radioId = radios[0].rid
        this._storageSet('musicRadioId', radioId)
      }

      this.radioId = radioId

      $.each(radios, (i, {rid, pic, name}) => {
        __temp += `<li data-rid=${rid}><img src="${pic}"><p>${name}</p></li>`
      })

      $musicRadios.html(__temp)
      $musicRadio.addClass('music__radio__on')
      $musicRadios.find(`li[data-rid="${radioId}"]`).addClass('playing')

      this._getSongList(radioId)
    }

    _updateTime (type, time) {
      time = $.isNumeric(time) ? time : 0
      if (type === 'ctime') {
        $musicCtime.html(this._secondToTime(time))
      }
      if (type === 'dtime') {
        $musicDtime.html(this._secondToTime(time))
      }
    }

    _updateBar (type, percentage) {
      percentage = percentage > 0 ? percentage : 0
      percentage = percentage < 1 ? percentage : 1
      if (type === 'played') {
        $musicPlayed.css({'width': `${percentage * 100}%`})
      }
      if (type === 'loaded') {
        $musicLoaded.css({'width': `${percentage * 100}%`})
      }
      if (type === 'volume') {
        $musicVolume.css({'width': `${percentage * 100}%`})
      }
    }

    _updateLrc (time) {
      let top = 0
      $musicLrc.find('p').each(function (i) {
        if (time >= $(this).data('time') - 0.5) {
          $(this).addClass('active').siblings('p').removeClass('active')
          top += $(this)[0].scrollHeight
        }
      })
      $musicLrc.parent().scrollTo({
        to: top,
        durTime: 500
      })
    }

    _goto (index) {
      if (typeof this.settings.songlist[index] === 'undefined') {
        index = 0
      }

      this._storageSet('musicPlayIndex', index)
      this.playIndex = index
      this.ended = false
      this.radioId = this._storageGet('musicRadioId') || 0

      const self = this
      const songs = this.settings.songlist[index]
      let _mp3Urls
      let _picUrl
      let _lrcUrl

      _mp3Urls = ['M800', 'M500', 'C400', 'C200'].map(v => `https://dl.stream.qqmusic.qq.com/${v}${songs.songmid}.mp3?vkey=${songs.vkey}&guid=5150825362&fromtag=1`)
      _picUrl = this.picUrl.replace('{albummid}', songs.albummid)
      _lrcUrl = this.lrcUrl.replace('{songid}', songs.songid)

      songs.pic = _picUrl
      audio = this._audio(_mp3Urls)

      if (!this.support || !audio) {
        this.trigger('error', '您的浏览器不支持 HTML5 音乐播放功能')
        return
      }

      this._updateInfo(songs)

      audio.preload = this.settings.preload ? this.settings.preload : 'metadata'

      if (this.settings.showlrc) {
        const lrcCache = this._storageGet(`lrc_${songs.songid.toString()}`)
        if (this._validTime(lrcCache)) {
          this._parseLrc(lrcCache.data)
        } else {
          this._loading(true)
          $.getJSON(_lrcUrl, ({data}) => {
            if (data) {
              self._storageSet(`lrc_${songs.songid.toString()}`, {
                data: data,
                timestamp: Date.parse(new Date()) / 1000
              })
              self._parseLrc(data)
            } else {
              self._parseLrc('[00:00.00]暂无歌词信息')
            }
            self._loading(false)
          })
        }
      }

      if (audio.readyState === 4) {
        audio.currentTime = 0
      }

      if (this.isMobile) {
        self._loading(false)
      }

      if (this.settings.autoplay || this.autoPlaying) {
        this.trigger('play')
      }

      $(audio).on('playing', () => {
        if (self.lrcTime) {
          clearInterval(self.lrcTime)
        }
        if (self.settings.showlrc) {
          self.lrcTime = setInterval(() => {
            self._updateLrc(audio.currentTime)
          }, 1000)
        }
      })

      $(audio).on('pause', () => {
        if (self.lrcTime) {
          clearInterval(self.lrcTime)
        }
      })

      $(audio).on('timeupdate', () => {
        self._updateTime('ctime', audio.currentTime)
        self._updateBar('played', audio.currentTime / audio.duration)
      })

      $(audio).on('progress', () => {
        const percentage = audio.buffered.length ? audio.buffered.end(audio.buffered.length - 1) / audio.duration : 0
        self._updateBar('loaded', percentage)
      })

      $(audio).on('canplay', () => {
        let volume = self._storageGet('musicVolume')
        if (!volume) {
          self._storageSet('musicVolume', 0.5)
          volume = 0.5
        }
        self.volume = volume
        audio.volume = volume
        self._loading(false)
        self._updateBar('volume', volume)
        self._updateTime('dtime', audio.duration)
      })

      $(audio).on('error', () => {
        self.trigger('error')
      })

      $(audio).on('ended', () => {
        self.ended = true
        self.trigger('next')
      })
    }

    _getSongList (radioId, update = false) {
      const self = this
      const songlist = this._storageGet('musicList')
      if (this._validTime(songlist) && !update) {
        this.settings.songlist = songlist.data
        this.total = songlist.data.length
        this._init()
      } else {
        this._loading(true)
        $.getJSON(`${this.radioUrl}&rid=${radioId}`, ({data}) => {
          if (data && $.isArray(data)) {
            self.settings.songlist = data
            self.total = data.length
            self._storageSet('musicList', {
              data: data,
              timestamp: Date.parse(new Date()) / 1000
            })
            self._init()
          }
          self._loading(false)
        })
      }
    }

    _radio () {
      const self = this
      const radios = this._storageGet('musicRadios')
      if (this._validTime(radios)) {
        this._updateRadios(radios.data)
      } else {
        this._loading(true)
        $.getJSON(this.radioUrl, ({data}) => {
          if (data && $.isArray(data)) {
            self._storageSet('musicRadios', {
              data: data,
              timestamp: Date.parse(new Date()) / 1000
            })
            self._updateRadios(data)
          } else {
            self.trigger('error', '╮（╯＿╰）╭ 电台载入失败')
          }
          self._loading(false)
        })
      }
    }

    play () {
      if (!this.playing) {
        this.playing = true
        $musicPlay.removeClass('play').addClass('pause')
        $musicList.find('li').removeClass('playing')
        $musicList.find('li').eq(this.playIndex).addClass('playing')
        $musicMute.addClass('mute-on')
        audio.volume = 0
        audio.play()
        this._fadeInSound(audio, 1000)
      }
    }

    pause () {
      if (this.playing || this.ended) {
        this.playing = false
        this.ended = false
        this._fadeOutSound(audio, 1000)
        $musicPlay.removeClass('pause').addClass('play')
        $musicMute.removeClass('mute-on')
        if (this.isMobile) {
          audio.pause()
        }
      }
    }

    prev () {
      this.playIndex--
      if (this.playIndex < 0) {
        this.playIndex = this.total - 1
      }
      this.autoPlaying = !!this.playing
      this.trigger('pause')
      audio.pause()
      this._goto(this.playIndex)
    }

    next () {
      this.playIndex++
      this.autoPlaying = !!this.playing
      this.trigger('pause')
      audio.pause()
      if (this.playIndex >= this.total) {
        this.playIndex = 0
        if ($.isNumeric(this.radioId) && (this.radioId > 1)) {
          this._storageSet('musicPlayIndex', 0)
          this._getSongList(this.radioId, true)
          return
        }
      }
      this._goto(this.playIndex)
    }

    error (title) {
      this._updateInfo({
        songname: title || '(°ー°〃) 音乐加载失败了'
      })
      this.trigger('pause')
    }

    trigger (event, params) {
      this[event](params)
    }
  }

  $.fn.scrollTo = function (options) {
    const defaults = {
      to: 0,
      durTime: 350,
      delay: 10,
      callback: null
    }
    const opts = $.extend(defaults, options)
    let timer = null
    const _this = this
    const curTop = _this.scrollTop()
    const subTop = opts.to - curTop
    let index = 0
    const dur = Math.round(opts.durTime / opts.delay)

    const smoothScroll = t => {
      index++
      const per = Math.round(subTop / dur)
      if (index >= dur) {
        _this.scrollTop(t)
        window.clearInterval(timer)
        if (opts.callback && typeof opts.callback === 'function') {
          opts.callback()
        }
      } else {
        _this.scrollTop(curTop + index * per)
      }
    }

    timer = window.setInterval(() => {
      smoothScroll(opts.to)
    }, opts.delay)
    return _this
  }

  $.fn.Player = function (options) {
    return new Player(this, options)
  }

  $('#music').Player({
    useRadio: true,
    autoplay: false
  })

  window.Zepto = window.$ = undefined
})(window.Zepto)
