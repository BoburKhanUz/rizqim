// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'alerts2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Alerts2 _$Alerts2FromJson(Map<String, dynamic> json) => Alerts2(
  openRoomHours: (json['open_room_hours'] as num?)?.toInt(),
  voidAmount: json['void_amount'] as String?,
  discountPercent: json['discount_percent'] as String?,
  unacceptedOnlineMinutes: (json['unaccepted_online_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$Alerts2ToJson(Alerts2 instance) => <String, dynamic>{
  'open_room_hours': instance.openRoomHours,
  'void_amount': instance.voidAmount,
  'discount_percent': instance.discountPercent,
  'unaccepted_online_minutes': instance.unacceptedOnlineMinutes,
};
