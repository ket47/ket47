<script type="text/javascript">
    App._Location_pickerModal={
        require:["https://api-maps.yandex.ru/2.1/?apikey=<?= getenv('yandex.mapkey') ?>&lang=ru_RU"],
        init:function(){
            ymaps.ready(App._Location_pickerModal.ymapsInit);
            this.node.find('.action_buttons').on('click',function(e){
                let $button=$(e.target);
                let action=$button.data('action');
                if( action ){
                    App._Location_pickerModal.actions[action] && App._Location_pickerModal.actions[action]();
                    return;
                }
            });
        },
        actions:{
            select:function(){
                if( !App._Location_pickerModal.data.addressSelected ){
                    alert("Отметьте адрес на карте");
                    return;
                }
                App._Location_pickerModal.handler.notify('selected',App._Location_pickerModal.data);
                App._Location_pickerModal.actions.close();
            },
            close:function(){
                App._Location_pickerModal.myMap.destroy();
                App.closeWindow(App._Location_pickerModal);
                delete App._Location_pickerModal;
            }
        },
        ymapsInit:function(){
            var myPlacemark;
            App._Location_pickerModal.myMap = new ymaps.Map('map', {
                center: [44.98, 34.18],
                zoom: 16
            }, {
                searchControlProvider: 'yandex#search'
            });

            // Слушаем клик на карте.
            App._Location_pickerModal.myMap.events.add('click', function (e) {
                var coords = e.get('coords');
                App._Location_pickerModal.data.coordsSelected=coords;
                // Если метка уже создана – просто передвигаем ее.
                if (myPlacemark) {
                    myPlacemark.geometry.setCoordinates(coords);
                }
                // Если нет – создаем.
                else {
                    myPlacemark = createPlacemark(coords);
                    App._Location_pickerModal.myMap.geoObjects.add(myPlacemark);
                    // Слушаем событие окончания перетаскивания на метке.
                    myPlacemark.events.add('dragend', function () {
                        getAddress(myPlacemark.geometry.getCoordinates());
                    });
                }
                getAddress(coords);
            });

            // Создание метки.
            function createPlacemark(coords) {
                return new ymaps.Placemark(coords, {
                    iconCaption: 'поиск...'
                }, {
                    preset: 'islands#violetDotIconWithCaption',
                    draggable: true
                });
            }

            // Определяем адрес по координатам (обратное геокодирование).
            function getAddress(coords) {
                myPlacemark.properties.set('iconCaption', 'поиск...');
                ymaps.geocode(coords).then(function (res) {
                    var firstGeoObject = res.geoObjects.get(0);
                    myPlacemark.properties
                            .set({
                                // Формируем строку с данными об объекте.
                                iconCaption: [
                                    // Название населенного пункта или вышестоящее административно-территориальное образование.
                                    firstGeoObject.getLocalities().length ? firstGeoObject.getLocalities() : firstGeoObject.getAdministrativeAreas(),
                                    // Получаем путь до топонима, если метод вернул null, запрашиваем наименование здания.
                                    firstGeoObject.getThoroughfare() || firstGeoObject.getPremise()
                                ].filter(Boolean).join(', '),
                                // В качестве контента балуна задаем строку с адресом объекта.
                                balloonContent: firstGeoObject.getAddressLine()
                            });
                    App._Location_pickerModal.data.addressSelected=firstGeoObject.getAddressLine();
                    App._Location_pickerModal.handler.notify('clicked',App._Location_pickerModal.data);
                });
            }
        }
        
    };
</script>
<div id="map" style="width: 100%; height: 70vh"></div>
<div class="action_buttons">
    <div class="primary" data-action="select">Выбрать адресс</div>
    <div class="secondary" data-action="close">Закрыть карту</div>
</div>