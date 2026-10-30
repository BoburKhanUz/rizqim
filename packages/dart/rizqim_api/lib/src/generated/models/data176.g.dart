// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data176.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data176 _$Data176FromJson(Map<String, dynamic> json) => Data176(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data176ToJson(Data176 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
