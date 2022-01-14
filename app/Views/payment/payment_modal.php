<script type="text/javascript">
    App._Home_paymentModal={
        init:function(){
            this.node.find('.action_buttons').on('click',function(e){
                let $button=$(e.target);
                let action=$button.data('action');
                if( action ){
                    App._Home_paymentModal.actions[action] && App._Home_paymentModal.actions[action]();
                    return;
                }
            });
            this.postToIframe(this.data,'/UniPayments/paymentLinkGet','unipay_iframe');
        },
        close:function(){

        },
        actions:{
            select:function(){
            },
            close:function(){
                App._Home_paymentModal.handler.notify('closed');
                App.closeWindow(App._Home_paymentModal);
                delete App._Home_paymentModal;
            }
        },
        postToIframe:function (data,url,target){
            $('body').append('<form action="'+url+'" method="post" target="'+target+'" id="postToIframe"></form>');
            $.each(data,function(n,v){
                $('#postToIframe').append('<input type="hidden" name="'+n+'" value="'+v+'" />');
            });
            $('#postToIframe').submit();
        }
    };
</script>
<iframe name="unipay_iframe" style="width: 100%; height: 70vh;border:none"></iframe>
<div class="action_buttons">
    <div class="secondary" data-action="close">Закрыть окно</div>
</div>