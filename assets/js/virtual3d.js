function openVpTab(evt, activeMode) {
  var i, tabcontent, tablinks;

  tabcontent = document.getElementsByClassName("vp-tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("vp-tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace("active", "");
  }
  document.getElementById(activeMode).style.display = "block";
  evt.currentTarget.className += " active";
}

function vpSubmit() {
  alert("Ole Clicked");
}
