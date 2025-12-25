validRange: function(nowDate) {
    return {
        start: '2000-01-01',
        end: '2100-01-01'
    };
},

eventAllow: function(dropInfo, draggedEvent) {
    const day = dropInfo.start.getDay(); // 0=zo, 6=za
    if (day === 0 || day === 6) {
        alert("Weekend is niet planbaar.");
        return false;
    }
    return true;
},

drop: function(info) {
    const day = info.date.getDay();
    if (day === 0 || day === 6) {
        alert("Weekend is niet planbaar.");
        info.revert();
        return;
    }
},
