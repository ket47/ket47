<html>
    <head>
        <script>
            const parent=window.parent;
            parent.postMessage('paymentNo','*')
            setTimeout(()=>{
                window.open("", "_self");
                window.close()
            },500)
        </script>
    </head>
    <body>
        <div style="display: flex;align-items:center;justify-content: center;height:100%">
            <img src="<?=getenv('app.baseUrl')?>/img/icons/fail.svg" style="width:40%"/>
        </div>
    </body>
</html>