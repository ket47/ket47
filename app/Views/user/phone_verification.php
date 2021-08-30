

<form method="get" action="/User/phoneVerificationSend/">
    Phone <input name="user_phone" value="+79787288233"><br>
    <button>Send Verification to Phone</button>
</form>

<form method="get" action="/User/phoneVerificationCheck/">
    Phone <input name="user_phone" value="+79787288233"><br>
    Confirmation code <input name="verification_code" value=""><br>
    <button>Verify Phone</button>
</form>