<?=view('home/header')?>
<div class="search_bar">
    <input type="search" placeholder="Filter">
</div>
<div class="user_list"></div>
<style>
    .user_card div{
        padding: 5px;
    }
    .user_card{
        box-shadow: 3px 5px 8px #ddd;
        border:solid 1px #ddd;
        border-radius: 5px;
    }
    .user_card_deleted{
        background-color: #fee;
    }
    .user_card_actions{
        margin: 10px;
    }
    input{
        padding: 3px;
    }
    .user_card input[type=text],input[type=email],input[type=tel],textarea{
        width:230px;
    }
    .search_bar input{
        width:100%;
        padding: 5px;
        border: 1px solid #ddd;
        background-color: #ffa;
    }
</style>
<script type="text/javascript">
    UserList={
        init:function (){
            $('.user_list').on('change',function(e){
                var $input=$(e.target);
                var name_parts=$input.attr('name').split('.');
                var name=name_parts[0];
                var user_id=name_parts[1];
                var subtype=name_parts[2];
                var value=UserList.val($input);
                if( subtype==='date' ){
                    value=value+' '+(UserList.val( $(`input[name='${name}.${user_id}.time']`) )||'00:00')+':00';
                }
                if( subtype==='time' ){
                    value=UserList.val( $(`input[name='${name}.${user_id}.date']`) )+' '+value+':00';
                }
                if( name==='user_group_id' ){
                    UserList.saveUserMemberGroup(user_id,subtype,value);
                } else {
                    UserList.saveUser(user_id,name,value);
                }
            });
            $('.search_bar').on('input',function(e){
                UserList.reload();
            });
            UserList.reload();
        },
        val:function( $input ){
            return $input.attr('type')==='checkbox'?($input.is(':checked')?1:0):$input.val();
        },
        saveUser:function (user_id,name,value){
            $.post('/User/itemUpdate',{user_id,name,value});
        },
        saveUserMemberGroup:function (user_id,user_group_id,value){
            $.post('/UserMemberGroup/itemUpdate',{user_id,user_group_id,value});
        },
        deleteUser:function( user_id ){
            $.post('/User/itemDelete',{user_id}).done(UserList.reload);
        },
        undeleteUser:function( user_id ){
            var name='deleted_at';
            $.post('/User/itemUpdate',{user_id,name}).done(UserList.reload);
        },
        reload_promise:null,
        reload:function(){
            if(UserList.reload_promise){
                UserList.reload_promise.abort();
            }
            var name_query=$('.search_bar input').val();
            var filter={
                name_query,
                limit:30
            };
            UserList.reload_promise=$.get('/Home/user_list',filter).done(function(response){
                $('.user_list').html(response);
            }).fail(function(error){
                $('.user_list').html(error);
            });
        }
    };
    $(UserList.init);
</script>
<?=view('home/footer')?>