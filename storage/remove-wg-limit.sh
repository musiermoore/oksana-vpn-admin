sudo tc filter delete dev wg0 protocol ip parent 1:0 prio "$2" u32 match ip dst "$1" flowid "1:$2"
