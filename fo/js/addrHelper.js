/* addrHelper.js цепляем авто-заполнение полей адреса с помощью сервиса DADATA + jquery.suggestions
* version 0.11.001 2025-06-26
*/
(function() {
    $(function() {
        // Token.init();
        // console.log("suggestions init...");
        var token = dadataUtl.token, // Token.get(),
            type = "ADDRESS",
            $suggestions = $("input.dadatahlp");

        // просто подсказки
        var suggestionsInstance = $suggestions.suggestions({
            token: token,
            type: type,
            hint: false,
            addon: "clear",
            noSuggestionsHint: false,
            onInvalidateSelection: function() {
                elemId = $(this).attr('id');
                // console.log("ON INVALIDATE SELECTION");
                // $("input.dd").val("");
            },
            onSelect: function(suggestion) {
                elemId = $(this).attr('id');
                if(typeof(cityCodes)=="undefined") cityCodes = [77,78];
                if(typeof(suggestion.data) !='undefined') {
                    // console.log('selected from input: ', elemId);
                    // console.log('DADATA FOUND ', suggestion.data); // debug view
                    var elParts = elemId.split("_"); // ddd,insr,reg,[1...N]
                    var fact = (elParts[2]==='fact') ? 'f':'';
                    var subNo = (typeof(elParts[3])!=='undefined') ? elParts[3] : '';
                    var baseNm = '#' + elParts[1] + fact;
                    var foundRegionId = false;
                    /*
                    if (suggestion.data.house_kladr_id) $("#dd_kladr").val(suggestion.data.house_kladr_id);
                    else if (suggestion.data.street_kladr_id) $("#dd_kladr").val(suggestion.data.street_kladr_id);
                    else if (suggestion.data.street_kladr_id) $("#dd_kladr").val(suggestion.data.street_kladr_id);
                    else $("#dd_kladr").val("");
                    */
                    if (suggestion.data.postal_code) $(baseNm+"adr_zip"+subNo).val(suggestion.data.postal_code); else $(baseNm+"adr_zip"+subNo).val("");
                    if (suggestion.data.country) asJs.setSelectByText(baseNm+"adr_countryid"+subNo, suggestion.data.country, true);
                    else asJs.setSelectByText(baseNm+"adr_countryid"+subNo, 'Россия',true);
                    // if (suggestion.data.federal_district) ...
                    var bRaion = true, bCity = true;
                    if (suggestion.data.region) {
                        if(suggestion.data.region_type_full === 'область') {
                            var fullRegName =  suggestion.data.region + ' область';
                        }
                        else // город, республика
                            var fullRegName = suggestion.data.region_type_full + ' '+suggestion.data.region;
                        if(suggestion.data.region_type_full === 'город') bRaion = false;
                        var setRegResult = asJs.setSelectByText(baseNm+"adr_country"+subNo, fullRegName);
                        if(!setRegResult && suggestion.data.region_type_full === 'город')
                          setRegResult = asJs.setSelectByText(baseNm+"adr_country"+subNo, ("г. "+suggestion.data.region) ); // запасной поиск по городу "г. Название"
                        if(setRegResult) foundRegionId = $(baseNm+"adr_country"+subNo).val();
                        // console.log("select region:", fullRegName, " result:", setRegResult, "foundRegion:", foundRegionId);
                        if(setRegResult && suggestion.data.region_type_full === 'город') bCity = false;

                    }
                    if(suggestion.data.city_type_full !='') {
                        bCity = true;
                    }
                    if(suggestion.data.city_district_type_full!='' && (cityCodes.indexOf(foundRegionId)<0)) {
                        bRaion = true;
                    }
                    if(bRaion) {
                      if(suggestion.data.city_district_with_type) $(baseNm+"adr_region"+subNo).val(suggestion.data.city_district_with_type);
                      else if (suggestion.data.area_with_type) $(baseNm+"adr_region"+subNo).val(suggestion.data.area_with_type);
                      else $(baseNm+"adr_region"+subNo).val("");
                    }
                    else $(baseNm+"adr_region"+subNo).val("");

                    //деревня и т.п. - settlement_with_type
                    if(suggestion.data.settlement_type_full!='') {
                      $(baseNm+"adr_city"+subNo).val(suggestion.data.settlement_with_type);
                    }
                    else if(cityCodes.indexOf(foundRegionId)>-1) $(baseNm+"adr_city"+subNo).val("");
                    if(bCity) {
                      // console.log("setting city...");
                      if(suggestion.data.city_type_full!='') $(baseNm+"adr_city"+subNo).val(suggestion.data.city_with_type);
                      else if (suggestion.data.settlement_with_type) $(baseNm+"adr_city"+subNo).val(suggestion.data.settlement_with_type);
                      else if (suggestion.data.city_with_type) $(baseNm+"adr_city"+subNo).val(suggestion.data.city_with_type);
                      else $(baseNm+"adr_city"+subNo).val("");
                    }
                    else $(baseNm+"adr_city"+subNo).val("");

                    if (suggestion.data.street_with_type) $(baseNm+"adr_street"+subNo).val(suggestion.data.street_with_type);
                    else if (suggestion.data.street) $(baseNm+"adr_street"+subNo).val(suggestion.data.street);
                    else $(baseNm+"adr_street"+subNo).val("");
                    if (suggestion.data.house) $(baseNm+"adr_house"+subNo).val(suggestion.data.house); else $(baseNm+"adr_house"+subNo).val("");
                    if (suggestion.data.block) $(baseNm+"adr_build"+subNo).val(suggestion.data.block);  else $(baseNm+"adr_build"+subNo).val("");
                    if (suggestion.data.flat) $(baseNm+"adr_flat"+subNo).val(suggestion.data.flat);  else $(baseNm+"adr_flat"+subNo).val("");
                    // if (suggestion.data.geo_lat) $("#dd_geo_lat").val(suggestion.data.geo_lat); else $("#dd_geo_lat").val("");
                    // if (suggestion.data.geo_lon) $("#dd_geo_lon").val(suggestion.data.geo_lon); else $("#dd_geo_lon").val("");
                    dadataUtl.hideId = elemId;
                    window.setTimeout(dadataUtl.hideInput, 200);
                }
                // else console.log('DADATA FAIL ', suggestion);


            }
        });
    });
})();
