<html>
    <head>
        <script>
            const parent=window.parent;
            parent.postMessage('paymentOk','*');
        </script>
    </head>
    <body>
        <div style="display: flex;align-items:center;justify-content: center;height:100%">
            <img src="<?=getenv('app.baseUrl')?>/img/icons/ok.svg" style="width:40%"/>
        </div>
    </body>
</html>