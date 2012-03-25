# Author: Rahul Devaskar

$ ->
  $.getJSON 'serverscripts/getData.php', (data) ->
  	# DOM holders
    latestbody = $ "#latestcross"
    old1body = $ "#old1cross"
    old2body = $ "#old2cross"

    # Templates
    latestTemplate = _.template ($ "#latesttemplate").html()
    oldTemplate = _.template ($ "#oldtemplate").html()

    # Format dates
    latestDate = formatDate data.data[0].date
    old1date = formatDate data.data[1].date
    old2date = formatDate data.data[2].date

    # Set headings
    ($ "#latestc").html "MACD Crossovers on " + latestDate
    ($ "#oldc1").html "MACD Crossovers on " + old1date
    ($ "#oldc2").html "MACD Crossovers on " + old2date

    # Rendering
    latestbody.html latestTemplate {"calls": data.data[0].calls}
    old1body.html oldTemplate {"calls": data.data[1].calls, "latest": data.c}
    old2body.html oldTemplate {"calls": data.data[2].calls, "latest": data.c}

  formatDate = (datestr) ->
    dateobj = new Date datestr
    months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October","November", "December"]
    return dateobj.getDate() + " " + months[dateobj.getMonth()] + ", " + dateobj.getFullYear()

  ($ "#twitterfollow").click (eventObj) ->
    do eventObj.preventDefault
    do eventObj.stopPropagation
    return false

  twttr.anywhere (a) ->
    (a "#twitterfollow").hovercards username: (a) -> return a.title

  # Load google +1 button
  d = document
  po = d.createElement 'script'
  po.type = 'text/javascript'
  po.async = true;
  po.src = 'https://apis.google.com/js/plusone.js'
  s = (d.getElementsByTagName 'script')[0]
  s.parentNode.insertBefore po, s

  # Facebook plugin
  if d.getElementById 'facebook-jssdk' then return
  js = d.createElement 'script'
  js.id = 'facebook-jssdk'
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=391910620819915"
  s.parentNode.insertBefore js, po