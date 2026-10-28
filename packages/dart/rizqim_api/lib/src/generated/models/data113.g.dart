// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data113.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data113 _$Data113FromJson(Map<String, dynamic> json) => Data113(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data113ToJson(Data113 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
