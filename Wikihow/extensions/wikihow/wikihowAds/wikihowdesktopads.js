WH.desktopAds = (function () {
	"use strict";
	var RR_REFRESH_TIME = 30000;
	var VIEW_REFRESH_TIME = 5000;
	var INTRO_REFRESH_TIME = 12000;
	var BOTTOM_MARGIN = 114;
	var adLabelHeight = 39;
	var rightRailElements = [];

	// keep track of the state of the right rail elements size
	// only check the size MaxCount times
	var rrSizeChanged = false;
	var rrSizeCheckCount = 0;
	var RR_SIZE_MAX_COUNT = 15;

    var rightRailExtra = null;

	var quizAds = {};
	var introAd;
	var bodyAds = [];
	var lastScrollPosition = window.scrollY;

	function isDocumentHidden() {
		var hidden = false;
		if (null!=document.hidden) {
			hidden = document.hidden;
		} else if (null!=document.mozHidden) {
			hidden = document.mozHidden;
		} else if (null!=document.webkitHidden) {
			hidden = document.webkitHidden;
		} else if (null!=document.msHidden) {
			hidden = document.msHidden;
		}
		return hidden;
	}

	function apsFetchBids(slotValues, gptSlotIds) {
		var gptSlots = [];
		for (var i = 0; i < gptSlotIds.length; i++) {
			gptSlots.push(gptAdSlots[gptSlotIds[i]]);
		}
		apstag.fetchBids({
			slots: slotValues,
			timeout: 2e3
		}, function(bids) {
			googletag.cmd.push(function(){
				apstag.setDisplayBids();
				for (var i = 0; i < gptSlots.length; i++ ) {
					var slot = gptSlots[i];
					setDFPTargeting(slot, dfpKeyVals);
				}
				googletag.pubads().refresh(gptSlots);
			});
		});
	}

	function apsLoad(ad) {
		var id = ad.adTargetId;
		var display = ad.lateLoad;
		var slotName = gptAdSlots[id].getName();
		var sizes = gptAdSlots[id].getSizes();
		var sizesArray = [];
		for (var i = 0; i < sizes.length; i++) {
			var sizesSub = [];
			sizesSub.push(sizes[i].getWidth());
			sizesSub.push(sizes[i].getHeight());
			sizesArray.push(sizesSub);
		}
		var slotsArray = [{slotID: id, slotName: slotName, sizes: sizesArray}];
		var gptSlotIds = [id];
		apsFetchBids(slotsArray, gptSlotIds);
	}

	function updateKeyVal(adId, key, value) {
		if (dfpKeyVals[gptAdSlots[adId].getName()]) {
			dfpKeyVals[gptAdSlots[adId].getName()][key] = value;
		}
	}

	function gptLoad(ad) {
		var id = ad.adTargetId;
		var display = ad.lateLoad;
		var refreshValue = ad.getRefreshValue();
		googletag.cmd.push(function() {
			// optionally call display first if dfp late loading is active
			if (display) {
				googletag.display(id);
			}
			updateKeyVal(id, 'refreshing', refreshValue);
			setDFPTargeting(gptAdSlots[id], dfpKeyVals);
			// the refresh call actually loads the ad
			googletag.pubads().refresh([gptAdSlots[id]]);
		});
	}

	function impressionViewable(slot) {
		var ad;
		for (var i = 0; i < rightRailElements.length; i++) {
			var tempAd = rightRailElements[i];
			if (gptAdSlots[tempAd.adTargetId] == slot) {
				ad = tempAd;
			}
		}
        if (!ad && introAd) {
            // check intro ad
			if (gptAdSlots[introAd.adTargetId] == slot) {
				ad = introAd;
			}
        }
		if (!ad) {
			return;
		}
		ad.adHeight = ad.adElement.offsetHeight;

		if (ad.refreshable && ad.viewablerefresh) {
			setTimeout(function() {ad.refresh();}, ad.refreshTime);
		}
	}

	function slotRendered(slot, size, e) {
		// look for right rail ads which are the only ones that will be moved/refreshed
		var ad;
		for (var i = 0; i < rightRailElements.length; i++) {
			var tempAd = rightRailElements[i];
			if (gptAdSlots[tempAd.adTargetId] == slot) {
				ad = tempAd;
			}
		}
		if (!ad) {
			return;
		}
		ad.adHeight = ad.adElement.offsetHeight;
        // don't even bother checking the space unless the ad is less than 300px in height
		var viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
        if (ad.extraChild && size && parseInt(size[1]) < 300) {
			ad.extraChild.style.visibility = "visible";
        } else if (ad.extraChild) {
            ad.extraChild.style.visibility = "hidden";
        }
		updateFixedPositioning(ad, viewportHeight, ad.last);

		if (ad.refreshable && ad.renderrefresh) {
			setTimeout(function() {ad.refresh();}, ad.refreshTime);
		}
	}

	function DFPInit() {
		(function() {
			var gads = document.createElement('script');
			gads.async = true;
			gads.type = 'text/javascript';
			var useSSL = 'https:' == document.location.protocol;
			gads.src = (useSSL ? 'https:' : 'http:') +
			'//www.googletagservices.com/tag/js/gpt.js';
			var node = document.getElementsByTagName('script')[0];
			node.parentNode.insertBefore(gads, node);
		})();
		googletag.cmd.push(function() {
			defineGPTSlots();
		});
	}

	function insertDFPLightAd(ad) {
		ad.adHeight = ad.asHeight;
		var i = window.document.createElement('div');
		i.setAttribute('data-glade', '');
		var path = ad.adunitpath;
		i.setAttribute('class', 'gptlight');
		i.setAttribute('data-ad-unit-path', path);
		i.setAttribute('width', 728);
		i.setAttribute('height', 90);
		var target = ad.adTargetId;
		var glade = document.createElement('script');
		glade.async = true;
		glade.src = 'https://securepubads.g.doubleclick.net/static/glade.js';
		window.document.getElementById(target).appendChild(i);
		window.document.getElementById(target).appendChild(glade);
	}

	function insertAdsenseAd(ad) {
		// set the height of he ad to the adsense height
		ad.adHeight = ad.asHeight;
		var client = "ca-pub-9543332082073187";
		var i = window.document.createElement('ins');
		i.setAttribute('data-ad-client', client);
		var slot = ad.slot;
		i.setAttribute('data-ad-slot', slot);
		i.setAttribute('class', 'adsbygoogle');
		var css = 'display:inline-block;width:'+ad.asWidth+'px;height:'+ad.asHeight+'px;';
		i.style.cssText = css;
		var target = ad.adTargetId;
		window.document.getElementById(target).firstElementChild.appendChild(i);
		var channels = ad.channels ? ad.channels: "";
		(adsbygoogle = window.adsbygoogle || []).push({
			params: {
				google_ad_channel: channels
			}
		});
	}

	function recalcAdHeights() {
		for (var i = 0; i < rightRailElements.length; i++) {
			var ad = rightRailElements[i];
			ad.adHeight = ad.adElement.offsetHeight;
		}
	}

	function Ad(adElement) {
		this.adElement = adElement;
		this.adHeight = this.adElement.offsetHeight;
		this.adTargetId = this.adElement.getAttribute('data-adtargetid');
		this.lateLoad = this.adElement.getAttribute('data-lateload') == 1;
		this.service = this.adElement.getAttribute('data-service');
		this.apsload = this.adElement.getAttribute('data-apsload') == 1;
		this.isLoaded = this.adElement.getAttribute('data-loaded') == 1;
		this.asWidth = this.adElement.getAttribute('data-adsensewidth');
		this.asHeight = this.adElement.getAttribute('data-adsenseheight');
		this.slot = this.adElement.getAttribute('data-slot');
		this.adunitpath = this.adElement.getAttribute('data-adunitpath');
		this.channels = this.adElement.getAttribute('data-channels');
		this.refreshable = this.adElement.getAttribute('data-refreshable') == 1;
		this.viewablerefresh = this.adElement.getAttribute('data-viewablerefresh') == 1;
		this.renderrefresh = this.adElement.getAttribute('data-renderrefresh') == 1;
		this.refreshtimeout = false;
		this.refreshNumber = 0;
        this.maxRefresh = false;
        this.refreshTime = RR_REFRESH_TIME;
		if (this.isLoaded) {
			this.refreshNumber++;
		}
		this.getRefreshValue = function() {
			if (this.refreshNumber == 0 && !this.refreshable) {
				return 'not';
			}

			this.refreshNumber++;
			if (this.refreshNumber > 20) {
				return 'max';
			}
			return this.refreshNumber.toString();
		};
		this.load = function() {
			// if already loaded do nothing
			if (this.isLoaded == true) {
				return;
			}
			if (this.service == 'dfp') {
				// if dfp was not already initialized do so now
				if (gptRequested == false) {
					DFPInit();
					gptRequested = true;
				}
				if (this.apsload) {
					apsLoad(this)
				} else {
					gptLoad(this)
				}
			} else if (this.service == 'dfplight') {
				insertDFPLightAd(this);
			} else {
				insertAdsenseAd(this);
			}
			this.isLoaded = true;
		};

		this.refresh = function() {
			var ad = this;
			if (isDocumentHidden()) {
				// check again later
				setTimeout(function() {ad.refresh();}, VIEW_REFRESH_TIME);
				return;
			}
			var lastScrollY = this.lastRefreshScrollY;
			this.lastRefreshScrollY = window.scrollY;
			// check if ad is in viewport
			var viewportHeight = (window.innerHeight || document.documentElement.clientHeight);
			var rect = this.element.getBoundingClientRect();
			if (!isInViewport(rect, viewportHeight, false, ad)) {
				// check again later
				setTimeout(function() {ad.refresh();}, VIEW_REFRESH_TIME);
				return;
			}
			var refreshValue = this.getRefreshValue();
			if (this.maxRefresh && refreshValue > this.maxRefresh) {
				this.refreshable = false;
				return;
			}
			updateKeyVal(this.adTargetId, 'refreshing', refreshValue);
			if (this.apsload) {
				apsLoad(this)
			} else {
				var id = this.adTargetId;
				var display = this.lateLoad;
				googletag.cmd.push(function() {
					setDFPTargeting(gptAdSlots[id], dfpKeyVals);
					googletag.pubads().refresh([gptAdSlots[id]]);
				});
			}
		};
	}

	function QuizAd(element) {
		if (!element) {
			return;
		}
		this.show = function() {
			this.adElement.style.display = 'block';
		};
		Ad.call(this, element);
	}

	function BodyAd(element) {
		Ad.call(this, element.parentElement);
		this.element = element;
	}

	function IntroAd(element) {
		Ad.call(this, element);
		this.element = element;
		this.refreshTime = INTRO_REFRESH_TIME;
        this.maxRefresh = 2;
		this.stickingHeaderElement = document.getElementsByClassName("firstadsticking")[0];
		this.isAnimating = false;
		this.hasAnimated = false;
		this.sticky = element.getAttribute('data-sticky') == 1;
	}

    function RightRailAd(element) {
		var adElement = element.getElementsByClassName('whad')[0];
		Ad.call(this, adElement);
		// store the right rail container element and height for use later
		this.height = element.offsetHeight;
		this.element = element;
		this.position = 'initial';
	}

	/*
	 *  check if either the top or the bottom of the element is in view
	 *  taking into account header
	 *  if for loading we add 20% to the size of the viewport
	 *  @param rect - the result of calling  of getBoundingClientRect() on the target element
	 *  @param viewportHeight - the current viewport height
	 *  @param forLoading - adds 20% to viewport size
	 *  @param ad - the ad we are checking
	 */
	function isInViewport(rect, viewportHeight, forLoading, ad) {
		var screenTop = WH.shared.TOP_MENU_HEIGHT;

		if (rect.height == 0) return false;

		if (forLoading) {
			var offset = viewportHeight;
			// for body ads load them even sooner
			if (ad instanceof BodyAd) {
				offset = viewportHeight * 2;
			}
			screenTop = 0 - offset;
			viewportHeight = viewportHeight + offset;
		}
		if (rect.top >= screenTop && rect.top <= viewportHeight) {
			return true;
		}
		if (rect.bottom >= screenTop && rect.bottom <= viewportHeight) {
			return true;
		}
		if (rect.top <= screenTop && rect.bottom >= viewportHeight) {
			return true;
		}

		// if this is the last ad, then if the top of the rec is less than screen top
		// meaning the top of the ad container is above the page, then we will
		// set the add to always in the viewport..faking that it's height is infinite
		if (ad.last && rect.top <= screenTop) {
			return true;
		}
		return false;
	}

	function updateAdLoading(ad, viewportHeight) {
		if (ad.isLoaded) {
			return;
		}
		var rect = ad.element.getBoundingClientRect();
		// check viewport size + additional 20% so we load before the video is in view
		if (isInViewport(rect, viewportHeight, true, ad)) {
			ad.load();
		}
	}

	function finishIntroSlide(ad) {
		ad.isAnimating = false;
		ad.hasAnimated = true;
		ad.adElement.style.position = 'static';
		ad.adElement.style.top = 'auto';
		ad.adElement.style.zIndex = 'auto';
		ad.adElement.style.backgroundColor = '#fff';
		ad.isFixed = false;
		ad.position = 'static';
		if (ad.stickingHeaderElement) {
			var rect = ad.stickingHeaderElement.getBoundingClientRect();
			if (rect.top < 150) {
				ad.stickingHeaderElement.className = 'sticking';
				ad.stickingHeaderElement.style.top = null;
			}
		}
	}
	function slideIntroAdUp(ad, start, end) {
		// check if we should just stop early
		var rect = ad.element.getBoundingClientRect();
		if (rect.top > adLabelHeight || start <= end) {
			finishIntroSlide(ad);
		} else {
			ad.isAnimating = true;
			var headerVal = start + 132;
			if (ad.stickingHeaderElement) {
				//ad.stickingHeaderElement.setAttribute('data-animating', 1);
				ad.stickingHeaderElement.style.top = headerVal+'px';
			}
			ad.adElement.style.top = start+'px';
			setTimeout(function(){
				slideIntroAdUp(ad, start - 1, end);
			}, 3);
		}
	}

	function updateFixedPositioningIntro(ad, viewportHeight) {
		var rect = ad.element.getBoundingClientRect();

		if (ad.isAnimating == true) {
			return;
		}
		if (rect.top <= adLabelHeight) {
			// pick some random spot for the thing to slide back up
			if (rect.top > -500) {
				if (ad.hasAnimated == true) {
					return;
				}
				ad.adElement.style.position = 'fixed';
				ad.adElement.style.top = adLabelHeight + 'px';
				ad.adElement.style.zIndex = '1000';
				ad.isFixed = true;
				ad.position = 'fixed';
			} else if (ad.position == 'fixed'){
				slideIntroAdUp(ad, adLabelHeight, -94);
			}
		} else {
			ad.adElement.style.position = 'static';
			ad.adElement.style.top = 'auto';
			ad.adElement.style.zIndex = 'auto';
			ad.adElement.style.backgroundColor = '#fff';
			ad.isFixed = false;
			ad.position = 'static';
		}
	}

	/*
	 * returns the height of the element (in this case the ad) which is
	 * useful so we do not have to call getBoundingClientRect multiple times
	 * when trying to get the height of all three ads
	 */
	function updateFixedPositioning(ad, viewportHeight) {
		var rect = ad.element.getBoundingClientRect();

		if (!isInViewport(rect, viewportHeight, false, ad)) {
			// if the container is not in the viewport then make sure it is not fixed pos
			if (ad.position == 'fixed') {
				ad.adElement.style.position = 'absolute';
				ad.adElement.style.top = '0';
				ad.adElement.style.bottom = 'auto';
				ad.position = 'top';
			}
			return rect.height;
		}

		var bottom = WH.shared.TOP_MENU_HEIGHT + parseInt(ad.adHeight);
		if (rect.bottom < bottom && !ad.last) {
			if (ad.position != 'bottom') {
				ad.adElement.style.position = 'absolute';
				ad.adElement.style.top = 'auto';
				ad.adElement.style.bottom = '0';
				ad.position = 'bottom';
			}
		} else if (rect.top <= WH.shared.TOP_MENU_HEIGHT) {
			if (ad.position != 'fixed') {
				ad.adElement.style.position = 'fixed';
				ad.isFixed = true;
				ad.position = 'fixed';
			}
			var topPx = WH.shared.TOP_MENU_HEIGHT;
			if (ad.last) {
				var adBottom = window.scrollY + WH.shared.TOP_MENU_HEIGHT + ad.adHeight;
				var offsetBottom = document.documentElement.scrollHeight - BOTTOM_MARGIN;
				if ( adBottom > offsetBottom ) {
					topPx = topPx - (adBottom - offsetBottom);
				}
			}
			ad.adElement.style.top = topPx + 'px';
		} else {
			if (ad.position != 'top') {
				ad.adElement.style.position = 'absolute';
				ad.adElement.style.top = '0';
				ad.adElement.style.bottom = 'auto';
				ad.position = 'top';
			}
		}

		return rect.height;
	}

	/*
	 * if we have 3 right rail elements check sidebar height vs article height
	 * to make sure the rr ads are not longer than the article
	 */
	function checkSidebarHeight(rightRailElements, adHeights) {
		if (rrSizeChanged) {
			return;
		}
		if (rrSizeCheckCount >= RR_SIZE_MAX_COUNT) {
			return;
		}
		if (document.readyState != 'complete') {
			return;
		}
		rrSizeCheckCount++;
		var sidebarHeight = 0;
		var sidebar = document.getElementById('sidebar');
		if (sidebar) {
			sidebarHeight = sidebar.offsetHeight;
		}
		var articleHeight = 0;
		var article = document.getElementById('article');
		if (article) {
			articleHeight = article.offsetHeight;
		}

		// subtract from each RR element if the sidebar is bigger than the article
		if (articleHeight > 0 && sidebarHeight > 0 && sidebarHeight > articleHeight) {
			var diff = sidebarHeight - articleHeight;
			diff = parseInt((diff + 10)/3);
			var removeElements = false;
			for (var i = 0; i < rightRailElements.length; i++) {
				var value = adHeights[i] - diff;
				if (value < 600) {
					removeElements = true;
					break;
				}
				rightRailElements[i].element.style.height = value + 'px';
			}
			if (removeElements == true) {
				for (var i = 1; i < rightRailElements.length; i++) {
					var el = rightRailElements[i].element;
					el.parentElement.removeChild(el);
				}
				rightRailElements.length = 1;
			}
			// set flag to not resize again
			rrSizeChanged = true;
		}
	}

	// this is registered by the scroll handler
	function updateVisibility() {
		lastScrollPosition = window.scrollY;
		var viewportHeight = (window.innerHeight || document.documentElement.clientHeight);

		// keep track of ad heights for possible use if they are too tall for the article
		var adHeights = [];
		for (var i = 0; i < rightRailElements.length; i++) {
			var ad = rightRailElements[i];
			updateAdLoading(ad, viewportHeight);
			adHeights[i] = updateFixedPositioning(ad, viewportHeight);
		}
		for (var i = 0; i < bodyAds.length; i++) {
			var ad = bodyAds[i];
			updateAdLoading(ad, viewportHeight);
		}

		// now for the intro ad
		if (introAd) {
			if (!introAd.stickingHeaderElement) {
				introAd.stickingHeaderElement = document.getElementsByClassName("firstadsticking")[0];
			}
			if (introAd.sticky) {
				updateFixedPositioningIntro(introAd, viewportHeight);
			}
		}

		checkSidebarHeight(rightRailElements, adHeights);
	}

	function init() {
		updateVisibility();
		window.addEventListener('scroll', WH.shared.throttle(updateVisibility, 10));
	}

	function addIntroAd(id) {
		var introElement = document.getElementById(id);
		introAd = new IntroAd(introElement);
		if (introAd.service == 'dfplight') {
			introAd.load();
		}
	}

    function addRightRailAd(id) {
        var rightRailElement = document.getElementById(id);
        var ad = new RightRailAd(rightRailElement);
        ad.last = true;
        if (rightRailElements.length > 0) {
            rightRailElements[rightRailElements.length -1].last = false;
        }
        rightRailElements.push(ad);
    }
    function addBodyAd(id) {
        var element = document.getElementById(id);
        var ad = new BodyAd(element);

        bodyAds.push(ad);
	}

	function RightRailElement(element) {
		this.adElement = element.getElementsByClassName('rr_inner')[0];
		this.element = element;
		this.height = element.offsetHeight;
		this.isLoaded = true;
	}

	function addRightRailElement(id) {
		var rightRailElement = document.getElementById(id);
		var elem = new RightRailElement(rightRailElement);
        elem.last = true;
        if (rightRailElements.length > 0) {
            rightRailElements[rightRailElements.length -1].last = false;
        }
		rightRailElements.push(elem);
	}

	function addQuizAd(id) {
		var innerAd = document.getElementById(id);
		var wrap = innerAd.parentElement;
		var ad = new QuizAd(wrap);
		var quizContainer = wrap.parentElement;
		quizAds[quizContainer.id] = ad;
		quizContainer.addEventListener("change", function(e) {
			var id = this.id;
			if (quizAds[id]) {
				quizAds[id].show()
				quizAds[id].load()
			}
		});
	}

    function addRightRailAdExtraData(id) {
        if (!rightRailElements.length) {
            return;
        }
        // make sure this is an ad
        var ad = rightRailElements[0];
        var item = document.getElementById(id);
        ad.adElement.appendChild(item);
        ad.extraChild = item;
    }

	function getIntroAd() {
		return introAd;
	}

	return {
		'init' :init,
		'addIntroAd': addIntroAd,
		'addRightRailAd': addRightRailAd,
		'addRightRailElement': addRightRailElement,
		'addRightRailAdExtraData': addRightRailAdExtraData,
		'addQuizAd': addQuizAd,
		'addBodyAd': addBodyAd,
		'getIntroAd' : getIntroAd,
		'slotRendered' : slotRendered,
		'impressionViewable' : impressionViewable,
		'apsFetchBids' : apsFetchBids
	};

})();
WH.desktopAds.init();