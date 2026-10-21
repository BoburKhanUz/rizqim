// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'alerts4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Alerts4 _$Alerts4FromJson(Map<String, dynamic> json) => Alerts4(
  openRoomHours: (json['open_room_hours'] as num?)?.toInt(),
  voidAmount: json['void_amount'] as String?,
  discountPercent: json['discount_percent'] as String?,
  unacceptedOnlineMinutes: (json['unaccepted_online_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$Alerts4ToJson(Alerts4 instance) => <String, dynamic>{
  'open_room_hours': instance.openRoomHours,
  'void_amount': instance.voidAmount,
  'discount_percent': instance.discountPercent,
  'unaccepted_online_minutes': instance.unacceptedOnlineMinutes,
};
