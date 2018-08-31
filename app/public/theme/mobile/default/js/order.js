//获取运费
function getFreight(){
  var csn_id = $('#consignee h4').data('id') || 0;
  $(".show-desc").removeClass("show-desc");
  $("#ins-"+$('#shipping_method').val()).addClass("show-desc");
  $.getJSON(freightApi, {csn_id:csn_id, shipping_id:$('#shipping_method').val()}, function(res){
    if(res.status == 'success') {
      $('#shipping-amount').text(res.amount);
      var totals = parseFloat($('#goods-amount').text()) + parseFloat(res.amount);

        var full_cut_list_len = 0;
        for(var  full_cut in full_cut_list){
            full_cut_list_len++;
        }
        for (var j = full_cut_list_len - 1;j >= 0; j--) {
          if (totals > full_cut_list[j]['order_fee']) {
              totals -= full_cut_list[j]['discount_fee'];
              $("#full_cut").html('<dt class="f14" id="full-cut-label">满减</dt>'+
                  '<dd class="c666 important"><i class="cny">- ¥</i><font class="f14" id="full_cut_discount_fee">'+ full_cut_list[j]['discount_fee'] +'</font></dd>').show();
              $("#discount_fee").text(full_cut_list[j]['discount_fee']);
            break;
          } else {
              $("#full_cut").html("").hide();
              $("#discount_fee").text(0);
          }
        }

      $('#total-amount').text(totals.toFixed(2));
    }
  });
}

//弹出收件人列表
function popCsnList(){
  $('#csnli').show().animate({left: 0}, 200, function(){$('#wrapper').hide()});
}

//新建收件人
function addCsn(){
  var container = $('#csnform');
  container.find('.main').html($('#csn-form-tpl').html());
  container.show().animate({left: 0}, 200, function(){
    getAreaSelect();
    $('#wrapper').hide();
    $('#csnli').css({display:'none', left:'100%'});
    showBuildings()
  });
}

//隐藏收件人表单
function hideCsnForm(){
  $('#wrapper').hide();
  $('#csnli').css({left:0,display:'block'});
  $('#csnform').animate({left:'100%'}, 200, function(){$(this).hide()});
}

//隐藏收件人列表
function hideCsnList(){
  $('#wrapper').show();
  $('#csnli').animate({left:'100%'}, 200, function(){$(this).hide()});
}

//触发选换收件人
function onChangeCsn(e){
  var container = $(e).parent();
  if(container.hasClass('checked')) return false;
  $.vdsConfirm({
    content: '您确定要使用此收件人地址吗?',
    ok: function(){
      var html = '<div class="unfold fr"><i class="iconfont">&#xe614;</i></div>';
      html += container.find('dd.m').html();
      $('#consignee .rc').html(html);
      container.siblings('.checked').removeClass('checked');
      container.addClass('checked');
      $('#wrapper').show();
      $('#csnli').animate({left:'100%'}, 200);
      getFreight();
    },
  });
}

//编辑收件人信息
function editCsn(e){
  $('#csnform .main').html($('#csn-form-tpl').html());
  var form = $('#csnform form'), data = $(e).data('json');
  form.find('select[name="school"] option').each(function (){
      if($(this).val() == data.school){
          $(this).attr("selected",true);
          showBuildings(data.school);
      }
  });
  form.find('select[name="building"] option').each(function (){
      if($(this).val() == data.building){
          $(this).attr("selected",true);
      }
  });
  form.find('input[name="id"]').val(data.id);
  form.find('input[name="receiver"]').val(data.receiver);
  form.find('input[name="mobile"]').val(data.mobile);
  form.find('input[name="building"]').val(data.building);
  form.find('input[name="room"]').val(data.room);
  setArea('province', null, data.province);
  setArea('city', {province: data.province}, data.city);
  setArea('borough', {province: data.province, city: data.city}, data.borough);
  $('.fill-info').hide();
  $('#csnform').show().animate({left: 0}, 200, function(){
    $('#wrapper').hide();
    $('#csnli').css({display:'none', left:'100%'});
  });
}

//保存收件人表单
function saveCsnForm(api){
  var form = $('#csnform form');
  if(checkCsnForm(form)){
    $.asynInter(api, form.serialize(), function(res){
      if(res.status == 'success'){
        res.data.json = JSON.stringify(res.data);
        res.data.province = form.find('select[name="province"] option').not(function(){return !this.selected}).text();
        res.data.city = form.find('select[name="city"] option').not(function(){return !this.selected}).text();
        res.data.borough = form.find('select[name="borough"] option').not(function(){return !this.selected}).text();
        res.data.checked = 0;
        var row = $('#csnli').find('#csnopt-'+res.data.id);
        if(row.size() > 0){
          if(row.hasClass('checked')){
            res.data.checked = 1;
            $('#consignee .rc').html(juicer($('#csn-checked-tpl').html(), res.data));
            getFreight();
          }
          row.remove();
        }
        $('.empty-address').hide();
        $('#csnli .opts').prepend(juicer($('#csn-row-tpl').html(), res.data)); 
        hideCsnForm();
      }else{
        $.vdsPrompt({content:res.msg});
      }
    });
  }
}