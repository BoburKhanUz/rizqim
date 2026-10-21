// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'alerts3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Alerts3 _$Alerts3FromJson(Map<String, dynamic> json) => Alerts3(
  openRoomHours: (json['open_room_hours'] as num?)?.toInt(),
  voidAmount: json['void_amount'] as num?,
  discountPercent: json['discount_percent'] as num?,
  unacceptedOnlineMinutes: (json['unaccepted_online_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$Alerts3ToJson(Alerts3 instance) => <String, dynamic>{
  'open_room_hours': instance.openRoomHours,
  'void_amount': instance.voidAmount,
  'discount_percent': instance.discountPercent,
  'unaccepted_online_minutes': instance.unacceptedOnlineMinutes,
};
