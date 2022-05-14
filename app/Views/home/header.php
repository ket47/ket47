<!DOCTYPE html>
<html>
    <head>
        <title></title>
        <script src="/js/jquery-3.5.1.min.js"></script>
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script>
        <script>
            $(document).ajaxComplete(function (event, xhr, settings) {
                if(xhr.status>299){
                    App.xhr.onresponse(xhr);
                }
            });
            App={
                xhr:{
                    onresponse:function(xhr){
                        if( xhr.responseText==='' ){
                            return;
                        }
                        if( !xhr.responseJSON ){
                            try{
                                xhr.responseJSON=JSON.parse(xhr.responseText);
                            }catch(e){
                                return;
                            }
                        }
                        if(xhr.responseJSON.messages){
                            let txt='';
                            let error;
                            try{
                                error=JSON.parse(xhr.responseJSON.messages.error);
                                for( let i in error){
                                    txt+=error[i]+'\n';
                                }
                                console.log(error);
                            }
                            catch(e){
                                txt=xhr.responseJSON.messages.error;
                            }
                            alert(txt);
                            return;
                        }
                        alert('Server error: '+xhr.status+'\n'+xhr.responseJSON.message);                        
                    }
                },
                createWindow:function(){
                    
                },
                closeWindow:function( module ){
                    module.node.hide();
                    //module.node.remove();
                    delete module;
                    $("#appWindowDimmer").hide();
                },
                loadWindow:function(path,data){
                    var id = path.replace(/\//g, '_');
                    if (!$('#' + id).length) {
                        $('#appWindowContainer').append('<div id="' + id + '" class="app_window"></div>');
                    }
                    $("#appWindowDimmer").show().click(
                        function(){App.closeWindow(App[id])}
                    );
                    return App.loadModule(path, data || {});
                },
                loadModule:function(path,data){
                    var id = path.replace(/\//g, '_');
                    var handler = $.Deferred();
                    if( App[id] ){
                        App.initModule(id,data,handler);
                    } else {
                        App[id] = {
                            id:id
                        };
                        $.get(path,function(html){
                            App.setHTML("#"+id,html);
                            if(App[id].require && App[id].require.length){
                                App.require(App[id].require,function(){
                                    App.initModule(id,data,handler);
                                });
                            } else {
                                App.initModule(id,data,handler);
                            }
                        });   
                    }
                    return handler.promise();	
                },
                initModule: function(id,data,handler){
                    App[id].data = data;
                    App[id].handler = handler;
                    App[id].node = $("#" + id);
                    App[id].init ? App[id].init(data, handler) : '';
                    App[id].node.show();
                    handler&&handler.notify('inited',App[id]);
                },
                setHTML:function( query, html ){
                    $(query).html(html);
                    $(query).find("script").each(function() { eval(this.text);} );
                },
                loadedScripts:[],
                require:function(urls,callback){
                    if(!urls){
                        callback&&callback();
                        return false;
                    }
                    var filesLeft=urls.length;
                    function ok(){
                        if( --filesLeft<=0){
                            callback&&callback();
                        }
                    }
                    for(var i in urls){
                        var url=urls[i];
                        if( Array.isArray(url) ){
                            var original_callback=callback;
                            callback=function(){
                                App.require(url,original_callback);
                            };
                            ok();
                        } else
                        if( url.indexOf('.css')>-1 ){
                            $('head').append( $('<link rel="stylesheet" type="text/css" />').attr('href', url) );
                            ok();
                        } else
                        if( App.loadedScripts.indexOf(url)>-1 ){
                            ok();
                        } else {
                            App.loadedScripts.push(url);
                            $.ajax({url: url,dataType: "script",cache: true,async:true}).done(function(a,b,c){
                                ok();
                            }).fail(function(a,b,c){
                                ok();
                                console.log('failed',b,c);
                            });
                        }
                    }
                },
            };

        </script>
        <link rel="stylesheet" href="https://ka-f.fontawesome.com/releases/v5.15.4/css/free.min.css?token=1825be3012">
        <link rel="stylesheet" href="https://ka-f.fontawesome.com/releases/v5.15.4/css/free-v4-shims.min.css?token=1825be3012">
        <link rel="stylesheet" href="https://ka-f.fontawesome.com/releases/v5.15.4/css/free-v4-font-face.min.css?token=1825be3012">
        <!--<script src="https://kit.fontawesome.com/1825be3012.js" crossorigin="anonymous"></script>-->
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Raleway:wght@300&display=swap');

            html,body{
                margin: 0px;
                padding: 0px;
                font-family: 'Raleway', sans-serif;
                background-color:#fcfcff;
            }
            a{
                text-decoration: none;
                color:#39f;
            }
            a:hover{
                text-decoration: underline;
            }
            .dash_menu{
                padding: 10px;
                height: calc(100vh-20px);
                background-color: #333;
                color:white;
            }
            .dash_menu a{
                color:#cef;
            }
            input,textarea,.form_value{
                padding: 5px;
            }
            input[type=text],input[type=email],input[type=tel],input[type=number],textarea{
                width:calc( 90% );;
            }
            .segment,segment{
                box-shadow: 3px 5px 8px #ccc;
                border:solid 1px #ccc;
                border-radius: 5px;
                background-color:#fff;
                padding: 10px;
                margin-top: 4px;
                margin-bottom: 4px;
            }
            .action_buttons>div{
                border-radius: 10px;
                padding: 10px;
                display: inline-block;
                cursor: default;
                min-width: 100px;
            }
            .action_buttons{
                text-align: center;
                padding: 10px;
            }
            
            
            
            
            .primary{
                background-color: #6cf;
            }
            .secondary{
                background-color: #ccc;
            }
            .negative{
                color:white;
                background-color: #f66;
            }
            .positive{
                background-color: #6d9;
            }
            h2,h3{
                -border-bottom: 1px #6cf solid;
            }
            
            
            
            
            .grid_header>div{
                border-bottom: 2px #6cf solid;
                font-weight: bold;
            }
            button{
                padding: 5px;
                border: #09f 1px solid;
            }
            .filter #item_name_search{
                width:calc( 100% - 10px );
                padding: 5px;
                border: 1px solid #ddd;
                background-color: #ffa;
            }
            .image_list{
                display: grid;
                grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
                grid-gap:5px;
            }
            .image_list>div{
                border:5px #fff solid;
                border-radius: 8px;
                overflow: hidden;
                height:90px;
                background: no-repeat center white;
                text-align: center;
            }
            .image_list>div.disabled{
                border:5px #999 solid;
            }
            .image_list>div.deleted{
                border:5px #f99 solid;
            }
            .vcenter{
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .app_window{
                border:4px #6cf solid;
                background-color: #fcfeff;
                border-radius: 8px;
                box-shadow: 3px 5px 8px #ccc;
                width: 70%;
                position: absolute;
                top: 10%;
                z-index: 200;
            }
            #appWindowContainer{
                position: fixed;
                z-index: 200;
                top:0px;
            }
            #appWindowDimmer{
                background-color: #ffffffcc;
                z-index: 100;
                width: 100vw;
                height: 100vh;
                display: none;

            }
        </style>
    </head>
    <body>
        <div id="appWindowContainer" class="vcenter">
            <div id="appWindowDimmer"></div>
        </div>