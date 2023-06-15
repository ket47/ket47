💬💬💬💬💬💬💬💬💬💬💬
<b>🧑Новый отзыв от клиента</b>
Покупатель <b><?=$user->user_name?></b> оставил отзыв на покупку в <b><?=$store->store_name?></b>

<b>Товар:</b> <a href="<?=getenv('app.frontendUrl')?>catalog/product-<?=$product->product_id?>"><?=$product->product_name?></a>

<b>Отзыв:</b> <i><?=$reaction->reaction_comment?></i>