// require('./songbird_v1.js');// Todo replace with node library import
// import $ from "jquery";

function paymentsValidated(data, jwt) {
  validateAction(payload, eresp, JSON.stringify(data), JSON.stringify(jwt));
  switch (data.ActionCode) {
    case "SUCCESS":
      //            console.count('success');
      validateAction(payload, eresp, JSON.stringify(data), JSON.stringify(jwt));
      // Handle successful transaction, send JWT to backend to verify
      break;
    case "NOACTION":
      //            console.count("NOACTION");
      // Handle no actionable outcome
      break;
    case "FAILURE":
      //            console.count("FAILURE");
      // Handle failed transaction attempt
      errorCallback(data);
      break;
    case "ERROR":
      //            console.count("ERROR");
      // Handle service level error
      errorCallback(data);
      break;
  }
}

function sendToVirtualPay(payload, onSuccess, onFailure, jwt) {
  successCallback = onSuccessVP;
  errorCallback = onFailureVP;
  console.log("sending");
  fetch("https://evirtualpay.com:65443/api/authenticate", {
    body: payload,
    credentials: "same-origin",
    mode: "cors",
    headers: {
      Accept: "application/xml",
      "Content-Type": "application/xml",
      USERNAME: $("#merchant_id").val(),
      PASSWORD: $("#api_key").val(),
    },
    method: "POST",
  })
    .then((response) => {
      return response.text();
    })
    .then((response) => {
      console.log(response);
      successCallback(response, jwt, payload);
    })
    .catch((error) => {
      errorCallback(error);
    });
}

function sendToVirtualPayValidate(payload, onSuccess, onFailure, jwt, url) {
  successCallback = onSuccessValidate;
  errorCallback = onFailureValidate;
  fetch("https://evirtualpay.com:65443/api/validate", {
    body: payload,
    credentials: "same-origin",
    mode: "cors",
    headers: {
      Accept: "application/xml",
      "Content-Type": "application/xml",
      USERNAME: $("#merchant_id").val(),
      PASSWORD: $("#api_key").val(),
    },
    method: "POST",
  })
    .then((response) => {
      return response.text();
    })
    .then((response) => {
      successCallback(response, jwt);
    })
    .catch((error) => {
      errorCallback(error);
    });
}

function getToken(payload, onSuccess, onFailure) {
  successCallback = onSuccess;
  errorCallback = onFailure;
  fetch("https://evirtualpay.com:65443/api/getClientToken", {
    body: payload,
    credentials: "same-origin",
    mode: "cors",
    headers: {
      Accept: "application/xml",
      "Content-Type": "application/xml",
      USERNAME: $("#merchant_id").val(),
      PASSWORD: $("#api_key").val(),
    },
    method: "POST",
  })
    .then((response) => {
      return response.text();
    })
    .then((response) => {
      successCallback(response, payload);
    })
    .catch((error) => {
      errorCallback(error);
    });
}

function sendMobileRequest(payload, onSuccess, onFailure, mid, api_key) {
  console.log(payload);
  successCallback = onSuccessMobile;
  errorCallback = onFailureMobile;
  fetch("https://evirtualpay.com:65443/api/mobileCheckout", {
    body: payload,
    credentials: "same-origin",
    mode: "cors",
    headers: {
      Accept: "application/xml",
      "Content-Type": "application/xml",
      USERNAME: mid,
      PASSWORD: api_key,
    },
    method: "POST",
  })
    .then((response) => {
      return response.text();
    })
    .then((response) => {
      successCallback(response, payload);
    })
    .catch((error) => {
      errorCallback(error);
    });
}
