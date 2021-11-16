<?= view('home/header') ?>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr">
    <div></div>
    <div class="segment">
        <h3>Phone Verification</h3>
        <form method="get" action="/User/phoneVerificationSend/">
            <div style="display: grid;grid-template-columns:auto 170px">
                <div>Phone</div> <div><input name="user_phone" value="+79787288233"></div>
                <div style="grid-column:1 / 2 span;text-align: center"><button>Send Verification to Phone</button></div>
            </div>
        </form>
        <hr>
        <form method="get" action="/User/phoneVerificationCheck/">
            <div style="display: grid;grid-template-columns:auto 170px">
                <div>Phone</div> <div><input name="user_phone" value="+79787288233"></div>
                <div>Confirmation code</div> <div><input name="verification_code" value=""></div>
                <div style="grid-column:1 / 2 span;text-align: center"><button>Verify Phone</button></div>
            </div>
        </form>
    </div>
    <div></div>
</div>
<?=
view('home/footer')?>