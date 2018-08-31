function showCartList(url) {
    $.asynInter(url, null, function (res) {
        if (res.status == 'success') {
            for (var index in res.cart.items) {
                if (res.cart.items[index].pre_sell == 1) {
                    console.log(1)
                    $('#pre-sell-tip').show();
                    break;
                }
            }
            $('#cart').append(juicer($('#item-tpl').html(), res.cart)).append($('#footact-tpl').html());
            totalCart();
            preserveSpace('footfixed');
            bindOperates();
        } else {
            $('#cart').append($('#nodata-tpl').html());
        }
    });
}

function bindOperates() {
    //删除商品
    $('#cart .remove').on('click', function () {
        var obj = $(this);
        $.vdsConfirm({
            content: '您确定要移除该商品吗?',
            ok: function () {
                obj.closest('li.cart-row').remove();
                if ($('.shortAge').length == 0) {
                    $('#shortage-title').remove();
                }
                updateCart();
            }
        });
    });
    //清空购物车
    $('#footfixed a.clear').on('click', function () {
        $.vdsConfirm({
            content: '您确定清空购物车吗?',
            ok: function () {
                $('#cart').empty();
                updateCart();
            }
        });
    });
    //改变数量
    $('#cart .qty a').on('click', function () {
        var qty = $(this).siblings('input'), qty_val = parseInt(qty.val());
        if ($(this).hasClass('minus')) {
            if (qty_val > 1) {
                qty.val(qty_val - 1);
            }
            else {
                $.vdsPrompt({content: '购买数量不能少于1件'});
            }
        } else {
            var stock = qty.data('stock');
            if (qty.val() < stock) {
                qty.val(qty_val + 1);
            } else {
                $.vdsPrompt({content: "购买数量不能超过 " + stock + " 件"});
                return false;
            }
        }
        $(this).data('json')
        updateCart();
    });
}

function updateCart() {
    if ($('#cart .cart-row').size() > 0) {
        var cookie = {};
        $('#cart .cart-row').each(function () {
            cookie[$(this).data('key')] = $(this).data('json');
        });
        setCookie('CARTS', JSON.stringify(cookie), 604800);
        totalCart();
    } else {
        setCookie('CARTS', '', -1);
        $('#cart').html($('#nodata-tpl').html());
        $('#footfixed').remove();
    }
}

function totalCart() {
    var amount = 0.00;
    $('#cart .cart-row').each(function (i, e) {
        var price = parseFloat($(e).find('.unit-price').text()), qty = parseInt($(e).find('.qty').find('input').val());
        amount += parseFloat(price * qty);
    });
    $('#cart-kinds').text($('.cart-row').size());
    $('#cart-amount').text(amount.toFixed(2));

    var full_cut_list_len = 0;
    for(var  full_cut in full_cut_list){
        full_cut_list_len++;
    }
    console.log(full_cut_list)
    for (var j = full_cut_list_len - 1;j >= 0; j--) {
        if (amount >= full_cut_list[j]['order_fee']) {
            //匹配到最后一级
            if (full_cut_list_len - 1 == j) {
                $("#cart-totals").html("已满" + addNumberStyle(full_cut_list[j]['order_fee']) + "元，可减" + addNumberStyle(full_cut_list[j]['discount_fee']) + "元");
                break;
            } else {
                $("#cart-totals").html("下单减" + addNumberStyle(full_cut_list[j]['discount_fee']) + "元，再买" + addNumberStyle(full_cut_list[j+1]['order_fee'] - amount) + "元可减" +addNumberStyle(full_cut_list[j+1]['discount_fee']) + "元");
                break;
            }
        } else {
            $("#cart-totals").html("再买" + addNumberStyle(full_cut_list[j]['order_fee'] - amount) + "元可减" + addNumberStyle(full_cut_list[j]['discount_fee']) + "元");
        }
    }
    return amount;
}

function addNumberStyle(number) {
    number = parseFloat(number).toFixed(2) * 1;
    return "<span class='full-cut-number'>" + number + "</span>";
}