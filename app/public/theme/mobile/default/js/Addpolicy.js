$(function () {
	function reset() {
		$("img.lazy").lazyload({
			// effect: "fadeIn"
		});
		for (var i = 0; i < dropload.length; i++) {
			dropload[i].resetload();
		}
	}
	//注册
	var registeSwiper = new Swiper('#registerCont', {
		speed: 500,
		// hashnav:true,
		replaceState: true,
		// hashnavWatchState:true,
		onSlideChangeStart: function () {
			$("#regUseBtn a").removeClass('regUseCurr');
			$("#regUseBtn a").eq(registeSwiper.activeIndex).addClass('regUseCurr');
		},
		onSlideChangeEnd: function (swiper) {
			reset();
		}
	})
	$("#regUseBtn a").on('touchstart mousedown', function (e) {
		e.preventDefault();
		$("#regUseBtn a").removeClass('regUseCurr');
		$(this).addClass('regUseCurr');
		registeSwiper.slideTo($(this).index());
		reset();
	})
	$("#regUseBtn a").click(function (e) {
		e.preventDefault();
	})

	//上拉加载
	var page = Array.apply(null, Array(100)).map(function(item, i) {
        return 1;
    });

	var dropload = [];
	Array.prototype.forEach.call($('.swiper-slide'), function (item, i) {
		var drop = $(item).dropload({
			scrollArea: window,
			loadDownFn: function (me) {
				var index = registeSwiper.activeIndex; //当前tab
				if (index == i) { //当前tab与对应的dropload匹配
					me.lock();
					page[index]++;
					var cate = parseInt($('.regUseCurr:first').data('cate'));
					var url = urlApi + "?cate=" + cate +"&page=" + page[index];
					$.ajax({
						type: 'GET',
						url: url,
						dataType: 'json',
						success: function (data) {
							if (data.length <= 0) {
								me.noData();
							} else {
								juicer.register("getDiscount", function (original_price, now_price) {
									return (original_price / now_price * 10).toFixed(1);
                                });
								var parent = $($(".baodanall").get(index));
								parent.append(juicer($("#goods-list-tpl").html(), {"list":data}));
								reset();
								// 每次数据加载完，必须重置
								setTimeout(function () {
									me.unlock();
								}, 1000);
							}

							me.resetload();
						},
						error: function (jqXHR, textStatus, errorThrown) {
							console.log("Error... " + textStatus + " " + errorThrown);
							me.unlock();
							me.resetload();
						}
					});
				}
			}
		});
		dropload.push(drop);
	});
})