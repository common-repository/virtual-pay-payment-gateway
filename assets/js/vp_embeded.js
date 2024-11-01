/**
 * VCard Processing
 */
function payVCard() {
  loadModal("block");
  var merchantID,
    requestID,
    orderDate,
    requestTime,
    customerName,
    customerPhoneNo,
    cardNumber,
    expiry,
    amount,
    currency,
    country,
    city,
    cvv,
    postalCode,
    stateCode,
    email,
    request_xml;

  merchantID = "merchant1";
  requestID = jQuery("#order_code").val();
  orderDate = jQuery("#requestDate").val();
  requestTime = jQuery("#requestTime").val();
  customerName =
    jQuery("#vp_first_name").val() + " " +jQuery("#vp_last_name").val();
  customerPhoneNo = jQuery("#vp_mobile").val();
  cardNumber = jQuery("#vpCard").val();
  expiry = jQuery("#cardMM").val() + jQuery("#cardYY").val();
  amount = jQuery("#vp_amount").val()*100;
  currency = jQuery("#vp_currency").val();
  country = jQuery("#vp_country").val();
  city = jQuery("#vp_city").val();
  cvv = jQuery("#cardCVV").val();
  postalCode = jQuery("#postalCode").val();
  stateCode = jQuery("#stateCode").val();
  email = jQuery("#vp_email").val();

  request_xml =
    "<?xml version= '1.0' encoding= 'utf-8'?><message>" +
    "<merchantID>" +
    merchantID +
    "</merchantID>" +
    "<requestID>" +
    requestID +
    "</requestID>" +
    "<date>" +
    orderDate +
    "</date>" +
    "<requestTime>" +
    requestTime +
    "</requestTime>" +
    "<customerName>" +
    customerName +
    "</customerName>" +
    "<customerPhoneNumber>" +
    customerPhoneNo +
    "</customerPhoneNumber>" +
    "<cardNumber>" +
    cardNumber +
    "</cardNumber>" +
    "<expiry>" +
    expiry +
    "</expiry>" +
    "<amount>" +
    amount +
    "</amount>" +
    "<redirectUrl>" +
    jQuery("#successUrl").val() +
    "</redirectUrl>" +
    "<timeoutUrl>" +
    jQuery("#cancelUrl").val() +
    "</timeoutUrl>" +
    "<currency>" +
    currency +
    "</currency>" +
    "<country>" +
    country +
    "</country>" +
    "<city>" +
    city +
    "</city>" +
    "<cvv>" +
    cvv +
    "</cvv>" +
    "<postalCode>" +
    postalCode +
    "</postalCode>" +
    "<stateCode>" +
    stateCode +
    "</stateCode>" +
    "<email>" +
    email +
    "</email>" +
    "<description>Virtual Pay Payment for order number " +
    requestID +
    "</description>" +
    "</message>";

  jQuery.ajax({
    type: "POST",
    url: jQuery("#url").val(),
    headers: {
      "Content-Type": "text/plain",
      Username: jQuery("#apikey").val(),
      Password: jQuery("#merch").val(),
    },

    data: request_xml,
    success: function (response) {
      //console.log(json_encode(response));
      var response3D = parseXmlToJson(response);
      //console.log(response3D["requestID"]);
      //process 3d
      let payload = {
        PaReq: response3D["Payload"],
        MD: response3D["requestID"],
        TermUrl: response3D["ValidateUrl"],
      };

      ProcessCard3DRequest(response3D["ACSUrl"], payload, "post");
      loadModal("none");
      //end 3d processing
    },
    error: function (xhr, status, error) {
      var err = eval("(" + xhr.responseText + ")");

    },
  }); //End of Ajax

  return false;
}
function parseXmlToJson(xml) {
  const json = {};
  for (const res of xml.matchAll(
    /(?:<(\w*)(?:\s[^>]*)*>)((?:(?!<\1).)*)(?:<\/\1>)|<(\w*)(?:\s*)*\/>/gm
  )) {
    const key = res[1] || res[3];
    const value = res[2] && parseXmlToJson(res[2]);
    json[key] = (value && Object.keys(value).length ? value : res[2]) || null;
  }
  return json;
}
/**
 * Process 3D Request
 */
function ProcessCard3DRequest(path, params, method) {
  const vp3DForm = document.createElement("form");
  vp3DForm.method = "POST";
  vp3DForm.action = path;
  vp3DForm.target = "_parent";

  for (const key in params) {
    if (params.hasOwnProperty(key)) {
      const hiddenField = document.createElement("input");
      hiddenField.type = "hidden";
      hiddenField.name = key;
      hiddenField.value = params[key];
      vp3DForm.appendChild(hiddenField);
    }
  }
  document.body.appendChild(vp3DForm);
  vp3DForm.submit();
}

/**
 * Mobile wallet processing
 */

function payWallet() {
  var walletRequest = {
    date: jQuery("#requestDate").val(),
    requestTime: jQuery("#requestTime").val(),
    country: jQuery("#vp_country").val(),
    amount: jQuery("#vp_amount").val(),
    redirectUrl: jQuery("#successUrl").val(),
    merchantID: jQuery("#apikey").val(),
    requestID: jQuery("#order_code").val(),
    description: "Virtual Store Mobile Checkput",
    currency: jQuery("#vp_currency").val(),
    customerPhoneNumber: jQuery("#vpMobileNo").val(),
    customerName:jQuery("#vp_first_name").val() + " "+jQuery("#vp_last_name").val(),
    network:jQuery("#network").val(),
  };
  loadModal("block");
  jQuery.ajax({
    url:jQuery('#urlmw').val(),
    headers: {
      Username: jQuery("#apikey").val(),
      Password: jQuery("#merch").val(),
      "Content-Type": "application/json",
    },
    method: "POST",
    dataType: "json",
    data: JSON.stringify(walletRequest),
    success: function (response) {
        console.log("Response is ",response);
        var obj = response;
       loadModal("none");
    },
    error: function (request, status, error) {
        alert(request.responseText);
    }
  });

  return false;
}

function loadModal(display) {
  var modal = document.getElementById("vp_modal");
  var btn = document.getElementById("myBtn");
  var span = document.getElementsByClassName("vp_close")[0];
  modal.style.display = display;
}
