#!/bin/bash

{

dirsystem=/srv/http/data/system
audiooutput=$( cat $dirsystem/audio-output )
audioaplayname=$( cat $dirsystem/audio-aplayname )

model=$( cat /proc/cpuinfo | grep Revision | tail -c 4 | cut -c 1-2 )
if [[ $model == 11 ]]; then  # RPi4
	aplay=$( aplay -l | grep '^card' )
elif [[ $model == 09 || $model == 0c ]]; then  # RPi Zero
	aplay=$( aplay -l | grep '^card' | grep -v 'IEC958/HDMI1\|bcm2835 ALSA\]$' )
else
	aplay=$( aplay -l | grep '^card' | grep -v 'IEC958/HDMI1' )
fi

file=/etc/mpd.conf
mixertype=$( grep mixer_type $file | cut -d'"' -f2 )
mpdconf=$( sed '/audio_output/,/}/ d' $file ) # remove all outputs

readarray -t lines <<<"$aplay"
for line in "${lines[@]}"; do
	device=$( echo $line | sed 's/card \(.*\):.*device \(.*\):.*/hw:\1,\2/' )
	card=${device:3:1}
	if [[ $mixertype == none ]]; then
		scontents=$( amixer -c $card scontents | grep -B1 'pvolume' | grep 'Simple' | awk -F"['']" '{print $2}' )
		readarray -t mixers <<<"$scontents"
		for mixer in "${mixers[@]}"; do
			amixer -c $card sset "$mixer" 0dB
		done
	fi
	aplayname=$( echo $line | awk -F'[][]' '{print $2}' )
	aplaynameL=$( echo "$aplay" | grep -c "$aplayname" )
	(( $aplaynameL > 1 )) && aplayname="$aplayname"-$(( ${device: -1} + 1 ))
	name=$aplayname
	mixer_control=
	extlabel=
	routecmd=
	i2sfile="/srv/http/settings/i2s/$aplayname"
	if [[ -e "$i2sfile" ]]; then
		mixer_control=$( grep mixer_control "$i2sfile"  | cut -d: -f2- )
		extlabel=$( grep extlabel "$i2sfile"  | cut -d: -f2- )
		[[ -n $extlabel ]] && name=$extlabel
		routecmd=$( grep route_cmd "$i2sfile" | cut -d: -f2 )
		[[ -n $routecmd ]] && eval ${routecmd/\*CARDID\*/$card}
	fi
	
	mpdconf+='

audio_output {
	name              "'$name'"
	device            "'$device'"
	type              "alsa"
	auto_resample     "no"
	auto_format       "no"'
	
	if [[ -n $mixer_control ]]; then
		mpdconf+='
	mixer_control     "'$mixer_control'"
	mixer_device      "hw:'$card'"'
	
	fi
	
	if [[ -e /srv/http/data/system/mpd-dop && ${aplayname:0:-2} != 'bcm2835 ALSA' ]]; then
		mpdconf+='
	dop               "yes"'
	
	fi
	
	mpdconf+='
}'

done

echo "$mpdconf" > $file

systemctl restart mpd mpdidle

curl -s -X POST 'http://127.0.0.1/pub?id=page' -d '{ "p": "mpd" }'

# skip on startup - called by usbdac.rules
if [[ -e /tmp/startup ]]; then
	rm /tmp/startup /srv/http/settings/usbdac
	exit
fi

# usb dac - last one is new one
if (( $# > 0 )); then
	usbdacfile=/srv/http/data/system/usbdac
	if [[ $1 == remove ]]; then
		name=$audiooutput
		rm -f $usbdacfile
	elif [[ $1 == add ]]; then
		echo $aplayname > $usbdacfile
	fi
	curl -s -X POST 'http://127.0.0.1/pub?id=notify' -d '{ "title": "Audio Output Switched", "text": "'"$name"'", "icon": "output" }'
fi

} &
