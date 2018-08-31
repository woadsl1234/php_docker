
var loadReady = false;

$(function () {
    $('#latsw').vdsTapSwapper(function () {
        $('#top-menus').show();
    }, function () {
        $('#top-menus').hide();
    });

    $(window).scroll(function () {
        var $currentWindow = $(window);
        //当前窗口的高度
        var windowHeight = $currentWindow.height();
        //当前滚动条从上往下滚动的距离
        var scrollTop = $currentWindow.scrollTop();
        //当前文档的高度
        var docHeight = $(document).height();

        //当 滚动条距底部的距离 + 滚动条滚动的距离 >= 文档的高度 - 窗口的高度
        //换句话说：（滚动条滚动的距离 + 窗口的高度 = 文档的高度）  这个是基本的公式
        if ((scrollTop) == docHeight - windowHeight) {
            if (loadReady) {
                return;
            }
            var obj = $('#srli');
            if (obj.data('cur') != obj.data('next')) {
                loadReady = true;
                showFalls();
            } else {
                $('#nomore').show();
            }
        }
    });
});

function showFilters() {
    $('html').css({overflow: 'hidden'});
    $('body').css({height: $(window).height(), overflow: 'hidden'});
    var container = $('#filters');
    container.show().animate({left: 0}, 100);
    container.find('.elm .ek').click(function () {
        $(this).addClass('cur').siblings('.cur').removeClass('cur');
        container.find('.elm .ev').hide();
        $(this).next('.ev').show();
    });
}

function closeFilters() {
    $('html').css({overflow: 'auto'});
    $('body').css({height: 'auto', overflow: 'auto'});
    $('#filters').animate({left: '100%'}, 100, function () {
        $(this).hide()
    });
}

function outSearch() {
    $('#searcher').hide();
    $('#wrapper').show();
}

//获取url中的参数
function getUrlParam(name) {
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)"); //构造一个含有目标参数的正则表达式对象
    var r = window.location.search.substr(1).match(reg);  //匹配目标参数
    if (r != null) return unescape(r[2]);
    return null; //返回参数值
}

var nodata = false;

function showFalls() {
    if (nodata) {
        return;
    }

    var container = $('#srli');
    var dataset = {page: container.data('next'), pernum: 10};
    var sort = getUrlParam('sort');
    sort && (dataset.sort = sort);

    $.asynList(searchApi, dataset, function (res) {
        if (res.list) {
            container.append(juicer($('#goods-tpl').html(), res));
            container.data('cur', dataset.page);
            container.data('next', dataset.page + 1);
        } else {
            $('#nomore').show();
            nodata = true;
        }

        loadReady = false;
    });
}