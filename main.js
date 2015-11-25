var info = new google.maps.InfoWindow();
var iconRed = 'red-dot.png';
var iconLtBlue = 'mm_20_gray.png';
var iconLtYellow = 'mm_20_yellow.png';
var iconLtPurple = 'mm_20_purple.png';
var iconGreen = 'green-dot.png';

var arrowIcons = [];
for (angle = 0; angle < 360; angle += 45)
{
    arrowIcons.push('arrow' + angle + '.png');
}

var trips = [];

function Trip(name, user)
{
    this.name = name;
    this.user = user;
    this.pcount = 0;
    this.ccount = 0;
    this.markers = [];
    this.polyline = new google.maps.Polyline({strokeColor: "#000000",
                                              strokeWeight: 3,
                                              strokeOpacity: 1,
                                              map: map});
}

Trip.prototype.lastMarker = function()
{
    return this.markers[this.markers.length - 1];
}

Trip.prototype.appendMarker = function(data, lastMarker)
{
    data.index = this.markers.length;
    data.trip = this;
    data.isFiltered = function()
    {
        var show = false;
        if (this['photo'])
            show = show || document.getElementById("photo").checked;
        if (this['comment'])
            show = show || document.getElementById("comment").checked;
        else if (!this['photo'])
            show = show || document.getElementById("normal").checked;
        return show;
    }
    var point = new google.maps.LatLng(data.latitude, data.longitude);
    var marker = new google.maps.Marker({position: point, visible: false});
    marker.updateIcon = function(lastMarker)
    {
        if ('iconurl' in this.data)
        {
            var icon = this.data['iconurl'];
        }
        else if (this.data.index == 0)
        {
            var icon = iconGreen;
        }
        else if (lastMarker !== undefined && lastMarker)
        {
            var icon = iconRed;
        }
        else if (showBearings && 'bearing' in this.data)
        {
            var direction = Math.floor((this.data.bearing + 22.5) / 45) % 8;
            var icon = arrowIcons[direction];
        }
        else if (this.data['photo'])
        {
            var icon = iconLtYellow;
        }
        else if (this.data['comment'])
        {
            var icon = iconLtPurple;
        }
        else
        {
            var icon = iconLtBlue;
        }
        this.setIcon(icon);
    }
    if (data.photo)
        this.pcount++;
    if (data.comments)
        this.ccount++;
    data.date = fromISO(data.timestamp);
    if (this.markers.length > 0) {
        data.distance = distance(this.lastMarker().getPosition(),
                                 point);
        var totalTime = (data.date.getTime() - this.markers[0].data.date.getTime()) / 1000;
    } else {
        data.distance = 0;
        var totalTime = 0;
    }
    data.distanceToHere = this.totalDistance() + data.distance;
    data.totalTime = (leadingZeros(totalTime / 3600) + ':' +
                      leadingZeros(totalTime / 60 % 60) + ':' +
                      leadingZeros(totalTime % 60))
    marker.addListener("click", function() {
        info.setContent(createMarkerText(data));
        info.open(map, marker);
    });
    marker.setMap(map);
    bounds.extend(marker.getPosition());
    marker.data = data;
    marker.updateIcon(lastMarker);
    this.markers.push(marker);
    this.polyline.getPath().push(point);
    document.getElementById("dis").innerHTML = toMiles(this.totalDistance()).toFixed(2);
    document.getElementById("time").innerHTML = this.lastMarker().data.totalTime;
    document.getElementById("pcount").innerHTML = this.pcount;
    document.getElementById("ccount").innerHTML = this.ccount;
    document.getElementById("avgspeed").innerHTML = toMiles(this.avgSpeed() * 3.6).toFixed(2);
    return marker;
}

Trip.prototype.totalDistance = function()
{
    if (this.markers.length > 0)
        return this.lastMarker().data.distanceToHere;
    else
        return 0;
}

Trip.prototype.avgSpeed = function()
{
    // Return m/s, the same as data.speed and this.totalSpeed
    if (this.markers.length > 0) {
        var totalTime = (this.lastMarker().data.date.getTime() - this.markers[0].data.date.getTime()) / 1000;
        return this.totalDistance() * 1000 / totalTime;
    } else {
        return 0;
    }
}

Trip.prototype.applyFilter = function()
{
    var visible = 0;
    if (document.getElementById("last20").checked)
        var maxVisible = 20;
    else
        var maxVisible = -1;
    for (i = this.markers.length - 1; i >= 0; i--)
    {
        var marker = this.markers[i];
        var show = marker.data.isFiltered();
        if (show)
        {
            visible++;
            if (maxVisible >= 0 && maxVisible < visible)
                show = false
        }
        marker.setVisible(show);
    }
}

Trip.prototype.loadJSON = function(tripData)
{
    if (this.markers.length > 0 && tripData.pos.length > 0)
        // Reset the icon for the last marker (if it exists) and new items are
        // going to be added
        this.lastMarker().updateIcon();
    console.log('Going to add ' + tripData.pos.length + ' entries');
    for (i = 0; i < tripData.pos.length; i++)
    {
        var entry = tripData.pos[i];
        entry.trip = this;
        this.appendMarker(entry, i === tripData.pos.length - 1);
    }
    this.applyFilter();
    if (tripData.pos.length > 0 && document.getElementById("follow").checked)
    {
        map.panTo(this.lastMarker().getPosition());
    }
}

function updateFromJSON(text)
{
    var data = JSON.parse(text);
    if ('error' in data)
    {
        console.log(text);
        document.getElementById("auto").checked = false;
        return false;
    }
    isRunning = setTimeout(update, 60 * 1000);
    for (var id in data.trips)
    {
        console.log('Load trip #' + id);
        var tripData = data.trips[id];
        if (!(id in trips))
        {
            console.log('Create new instance: ' + tripData.name);
            trips[id] = new Trip(tripData.name, data.users[tripData.uid].name);
        }
        trips[id].loadJSON(tripData);
    }
}

var isRunning = null;
function switchAutomatic()
{
    if (isRunning !== null)
    {
        clearTimeout(isRunning);
        isRunning = null;
    }
    if (document.getElementById("auto").checked)
    {
        update();
    }
}

function update()
{
    var trip = trips[tripid];
    if (trip.markers.length > 0)
        var start = '&start=' + trip.lastMarker().data.timestamp;
    else
        var start = ''
    if (tripid === '*')
        start += '&onetrip='
    query('track.php?userid=' + userid + '&tripid=' + tripid + start,
          updateFromJSON)
}

function createMarkerText(data)
{
    if (useMetric) {
        var speedUnit = lang.get('unit-speed-metric');
        var heightUnit = lang.get('unit-height-metric');
        var distanceUnit = lang.get('unit-distance-metric');
    } else {
        var speedUnit = lang.get('unit-speed-imperial');
        var heightUnit = lang.get('unit-height-imperial');
        var distanceUnit = lang.get('unit-distance-imperial');
    }
    var html = ("<table border='0'><tr><td align='center'><b>" + lang.get('balloon-user') + ": </b>" +
                data.trip.user + "</td><td align='right'><b>" + lang.get('balloon-trip') + ": </b>" + data.trip.name +
                "</td></tr><tr><td colspan='2'><hr width='400'><\/td><\/tr><tr>" +
                "<td align='left'><b>" + lang.get('balloon-time') + ": </b>" + data.formattedTS +
                "</td><td align='right'><b>" + lang.get('balloon-total-time') + ": </b>" +
                data.totalTime + "</td></tr>");
    speed = toMiles(data.speed * 3.6);
    avgSpeed = toMiles(data.trip.avgSpeed() * 3.6);
    totalDistance = toMiles(data.distanceToHere);
    altitude = toFeet(data.altitude);
    html += ("<tr><td align='left'><b>" + lang.get('balloon-speed') + ": </b>" + speed.toFixed(2) + " " + speedUnit +
             "</td><td align='right'><b>" + lang.get('balloon-avg-speed') + ": </b>" + avgSpeed.toFixed(2) + " " + speedUnit +
             "</td></tr><tr><td align='left'><b>" + lang.get('balloon-altitude') + ": </b>" + altitude.toFixed(2) + " " + heightUnit +
             "</td><td align='right'><b>" + lang.get('balloon-total-distance') + ": </b>" + totalDistance.toFixed(2) + " " + distanceUnit +
             "</td></tr>");
    if (data.comment)
    {
        html += ("<tr><td colspan='2' align='left' width='400'><b>" +
                 lang.get('balloon-comment') + ":</b> " + data.comment + "</td></tr>");
    }
    html += "        <tr><td colspan='2'>" + lang.get('balloon-point') + " " + lang.get('balloon-point-val', data.index + 1, data.trip.markers.length) + "</td></tr>";
    if (data.photo)
    {
        html += ("    <tr><td colspan='2'><a href='" + data.photo +
                 "' target='_blank'><img src='" + data.photo +
                 "' width='200' border='0'></a></td></tr>");
    }
    html += "        <tr><td colspan='2'>&nbsp;<\/td><\/tr><\/table>";
    return html
}

function leadingZeros(number)
{
    number = Math.floor(number)
    if (number < 10)
        return '0' + number
    else
        return number
}

function fromISO(isoDate)
{
    var match = /(\d{4})-(\d?\d)-(\d?\d)[ _](\d?\d):(\d?\d):(\d?\d)/.exec(isoDate);
    return new Date(match[1], match[2] - 1, match[3], match[4], match[5], match[6]);
}

var query = function(url, callback)
{
    var req = new XMLHttpRequest();
    req.onreadystatechange = function() {
        if (req.readyState == 4 && req.status == 200)
            callback(req.responseText);
    };
    req.open('GET', url, true);
    req.send(null);
}

/* Math helpers */

function toMiles(distance)
{
    if (!useMetric)
        distance *= 0.621371192;
    return distance
}

function toFeet(distance)
{
    if (!useMetric)
        distance *= 3.2808399;
    return distance
}

function toRadians (angle) {
  return angle * (Math.PI / 180);
}

function toDegrees (angle) {
  return angle * (180 / Math.PI);
}

function distance(point1, point2) {
    lat1 = toRadians(point1.lat());
    lat2 = toRadians(point2.lat());
    delta_lon = toRadians(point1.lng() - point2.lng());
    if (Math.abs(lat1 - lat2) < 0.0000001 && Math.abs(delta_lon) < 0.0000001)
        return 0;
    dist = Math.sin(lat1) * Math.sin(lat2) + Math.cos(lat1) * Math.cos(lat2) * Math.cos(delta_lon);
    // Previous it was 1.1515 statue miles/min (1 min = 1/60 deg)
    // This is the corresponding radius in kilometers
    return Math.acos(dist) * 6370.69349;  // Average Earth radius in km
}
